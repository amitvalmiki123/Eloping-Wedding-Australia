<?php
/**
 * Plugin Name: Eloping SEO Form Bulk Updater
 * Plugin URI:  https://elopingweddings.com.au/
 * Description: One-click bulk update of the "Form Shortcode" fields (Hero + CTA) on every page that uses the SEO Landing template. Replaces the Contact Form 7 shortcode with the same Studio Ninja iframe embed used on the Contact Us page, so the form stays consistent across all SEO landing pages. Touches ONLY those two fields on those pages — no menus, no header/footer, no other pages, no other fields, no theme changes.
 * Version:     1.1.0
 * Author:      Eloping Weddings
 * License:     GPL-2.0-or-later
 * Text Domain: eloping-seo-form-updater
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

/* =========================================================================
 * CONSTANTS
 * ========================================================================= */

if ( ! defined( 'ELFU_TEMPLATE_FILE' ) ) {
    // The page template that identifies an "SEO landing page".
    define( 'ELFU_TEMPLATE_FILE', 'template-seo-landing.php' );
}

if ( ! defined( 'ELFU_BACKUP_OPTION' ) ) {
    define( 'ELFU_BACKUP_OPTION', 'eloping_seo_form_updater_backup' );
}

if ( ! defined( 'ELFU_NOTICE_TRANSIENT' ) ) {
    define( 'ELFU_NOTICE_TRANSIENT', 'eloping_seo_form_updater_notice' );
}

/* =========================================================================
 * FORM FIELDS
 *
 * The "Form Shortcode" fields (ACF) on the SEO Landing template that hold
 * the hero banner form and the bottom CTA band form
 * (registered in the child theme, inc/seo-landing-acf.php).
 * ========================================================================= */

/**
 * List of ACF field names to update. Filterable if you ever want to
 * narrow or widen the set (e.g. hero only).
 */
function elfu_form_fields() {
    $fields = array(
        'seo_hero_form' => __( 'Hero banner — "Form Shortcode" field', 'eloping-seo-form-updater' ),
        'seo_cta_form'  => __( 'Bottom CTA band — "Form Shortcode" field', 'eloping-seo-form-updater' ),
    );
    return apply_filters( 'eloping_seo_form_fields', $fields );
}

/* =========================================================================
 * TARGET VALUE — the exact iframe embed used on the Contact Us page
 * (source: the contact page's "contact_form_iframe" ACF field).
 * ========================================================================= */

/**
 * The iframe code that will be written to every form field of every
 * SEO landing page. Filterable if the Studio Ninja form ever changes.
 */
function elfu_target_iframe_code() {
    $code = <<<HTML
<iframe
    height="693"
    style="min-width: 100%; max-width: 600px; border: 0;"
    id="sn-form-m1co4"
    src="https://app.studioninja.co/contactform/parser/0a800fc9-a1a6-1d6c-81a1-c80ad5b35d26/0a800fc9-a1a6-1d6c-81a1-c80ad5d25d28"
    allowfullscreen>
</iframe>

<script
    type="text/javascript"
    data-iframe-id="sn-form-m1co4"
    src="https://app.studioninja.co/client-assets/form-render/assets/scripts/iframeResizer.js">
</script>
HTML;
    return apply_filters( 'eloping_seo_form_iframe_code', $code );
}

/**
 * True when a saved value already IS the Studio Ninja iframe embed
 * (whitespace-tolerant, so a manually pasted copy is recognised too).
 */
function elfu_is_iframe_value( $value ) {
    return false !== strpos( (string) $value, 'studioninja.co/contactform/parser/' );
}

/* =========================================================================
 * STORAGE DETECTION
 *
 * This site stores the SEO Landing ACF values as PLAIN post meta:
 *     seo_hero_form  => the value
 *     _seo_hero_form => field_seo_hero_form   (field-key reference)
 * (verified from the site's content export — there is no _acf_ prefix on
 * this install). For robustness the plugin also understands the standard
 * ACF storage (_acf_{name}) and always writes to whichever key the page
 * is actually using.
 * ========================================================================= */

/**
 * Return the meta key that holds a page's value for the given field name.
 * Falls back to this site's convention (plain post meta) when the value
 * does not exist yet.
 */
function elfu_field_value_key( $pid, $field ) {
    if ( metadata_exists( 'post', $pid, $field ) ) {
        return $field; // this site's convention
    }
    if ( metadata_exists( 'post', $pid, '_acf_' . $field ) ) {
        return '_acf_' . $field; // standard ACF convention
    }
    return $field; // default: this site's convention
}

/* =========================================================================
 * PAGE QUERY — ONLY pages using the SEO Landing template
 * ========================================================================= */

/**
 * All page IDs that use the SEO Landing template (any status).
 * This is the complete and only set of pages this plugin can touch.
 */
function elfu_seo_landing_page_ids() {
    return get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => ELFU_TEMPLATE_FILE,
        'no_found_rows'  => true,
    ) );
}

/* =========================================================================
 * STATUS (for the admin screen)
 * ========================================================================= */

/**
 * Classify every SEO landing page's current value per form field.
 *
 * Returns:
 *   array(
 *     'total_pages' => int,
 *     'fields'      => array( field => array(
 *        'label','iframe','cf7','empty','other','pending' ) ),
 *     'pending_total' => int, // total field-cells that will be written
 *   )
 */
function elfu_status() {
    $fields   = elfu_form_fields();
    $pages    = elfu_seo_landing_page_ids();
    $result   = array(
        'total_pages'   => count( $pages ),
        'fields'        => array(),
        'pending_total' => 0,
    );

    foreach ( $fields as $field => $label ) {
        $counts = array(
            'label'   => $label,
            'iframe'  => 0, // already the Studio Ninja iframe (incl. manually updated)
            'cf7'     => 0, // still the Contact Form 7 shortcode
            'empty'   => 0, // field unset / blank
            'other'   => 0, // anything else
            'pending' => 0, // will be written
        );
        foreach ( $pages as $pid ) {
            $key     = elfu_field_value_key( $pid, $field );
            $current = (string) get_post_meta( $pid, $key, true );
            if ( elfu_is_iframe_value( $current ) ) {
                $counts['iframe']++;
            } elseif ( '' === trim( $current ) ) {
                $counts['empty']++;
                $counts['pending']++;
            } elseif ( false !== strpos( $current, 'contact-form-7' ) ) {
                $counts['cf7']++;
                $counts['pending']++;
            } else {
                $counts['other']++;
                $counts['pending']++;
            }
        }
        $result['fields'][ $field ]    = $counts;
        $result['pending_total']      += $counts['pending'];
    }
    return $result;
}

/* =========================================================================
 * THE BULK UPDATE
 * ========================================================================= */

/**
 * Write the Studio Ninja iframe embed into every form field of every
 * SEO landing page that does not already have it.
 *
 * Safety rules:
 *  - Only pages with _wp_page_template = template-seo-landing.php.
 *  - Only the listed "Form Shortcode" fields (never any other field).
 *  - Cells that already contain the iframe are left completely alone.
 *  - Every value that IS changed is backed up first (one time) so the
 *    one-click Revert restores the originals exactly.
 *
 * Backup shape: option => array( pid => array( field => array(
 *     'key','value','existed','ref_added','ts' ) ) )
 */
function elfu_run_update() {
    $fields   = elfu_form_fields();
    $target   = elfu_target_iframe_code();
    $pages    = elfu_seo_landing_page_ids();
    $backup   = get_option( ELFU_BACKUP_OPTION, array() );
    if ( ! is_array( $backup ) ) {
        $backup = array();
    }

    $updated = 0;
    $skipped = 0;

    foreach ( $pages as $pid ) {
        foreach ( array_keys( $fields ) as $field ) {
            $key     = elfu_field_value_key( $pid, $field );
            $current = (string) get_post_meta( $pid, $key, true );

            // Already the Studio Ninja iframe (e.g. updated manually) — skip.
            if ( elfu_is_iframe_value( $current ) ) {
                $skipped++;
                continue;
            }

            // Back up the original value exactly once (keep the earliest one).
            if ( ! isset( $backup[ $pid ][ $field ] ) ) {
                $backup[ $pid ][ $field ] = array(
                    'key'       => $key,
                    'value'     => $current,
                    'existed'   => metadata_exists( 'post', $pid, $key ),
                    'ref_added' => false,
                    'ts'        => current_time( 'mysql' ),
                );
            }

            update_post_meta( $pid, $key, $target );

            // Keep the field-key reference meta consistent with every other
            // SEO page on this install (only add it when creating a value
            // where none existed at all).
            if ( $field === $key && ! $backup[ $pid ][ $field ]['existed'] && ! metadata_exists( 'post', $pid, '_' . $field ) ) {
                add_post_meta( $pid, '_' . $field, 'field_' . $field, true );
                $backup[ $pid ][ $field ]['ref_added'] = true;
            }

            $updated++;
        }
    }

    update_option( ELFU_BACKUP_OPTION, $backup, false );

    return array(
        'updated'     => $updated,
        'skipped'     => $skipped,
        'total_pages' => count( $pages ),
    );
}

/* =========================================================================
 * THE ONE-CLICK REVERT
 * ========================================================================= */

/**
 * Restore every backed-up original value (per page, per field), then
 * clear the backup. Returns the number of field-cells restored.
 */
function elfu_run_revert() {
    $backup   = get_option( ELFU_BACKUP_OPTION, array() );
    $restored = 0;

    if ( is_array( $backup ) ) {
        foreach ( $backup as $pid => $fields_backup ) {
            if ( ! is_numeric( $pid ) || ! get_post( (int) $pid ) || ! is_array( $fields_backup ) ) {
                continue; // page deleted or malformed entry — drop it
            }
            foreach ( $fields_backup as $field => $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['key'] ) ) {
                    continue;
                }
                if ( ! empty( $entry['existed'] ) ) {
                    update_post_meta( (int) $pid, $entry['key'], (string) $entry['value'] );
                } else {
                    // The field did not exist before the update — remove it.
                    delete_post_meta( (int) $pid, $entry['key'] );
                    if ( ! empty( $entry['ref_added'] ) ) {
                        delete_post_meta( (int) $pid, '_' . $field );
                    }
                }
                $restored++;
            }
        }
    }

    delete_option( ELFU_BACKUP_OPTION );
    return $restored;
}

/* =========================================================================
 * ADMIN UI — single menu page, action buttons
 * ========================================================================= */

add_action( 'admin_menu', 'elfu_admin_menu' );
function elfu_admin_menu() {
    add_menu_page(
        __( 'SEO Form Update', 'eloping-seo-form-updater' ),
        __( 'SEO Form Update', 'eloping-seo-form-updater' ),
        'manage_options',
        'eloping-seo-form-updater',
        'elfu_render_page',
        'dashicons-forms',
        59
    );
}

add_action( 'admin_init', 'elfu_handle_actions' );
function elfu_handle_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Run the bulk update.
    if ( isset( $_POST['eloping_seo_form_update'] )
        && 'run' === $_POST['eloping_seo_form_update']
        && check_admin_referer( 'eloping_seo_form_update', 'eloping_seo_form_update_nonce' ) ) {
        $result = elfu_run_update();
        set_transient(
            ELFU_NOTICE_TRANSIENT,
            array(
                'type'    => 'success',
                'message' => sprintf(
                    /* translators: 1: updated field-cells, 2: skipped cells, 3: page count */
                    __( 'Done — %1$d form field(s) updated with the Contact-page iframe form across %3$d SEO landing page(s). %2$d field(s) already had the iframe and were skipped. A rollback backup was saved; use the Revert button on this screen to undo.', 'eloping-seo-form-updater' ),
                    (int) $result['updated'],
                    (int) $result['skipped'],
                    (int) $result['total_pages']
                ),
            ),
            120
        );
        wp_safe_redirect( admin_url( 'admin.php?page=eloping-seo-form-updater' ) );
        exit;
    }

    // Revert to the backed-up originals.
    if ( isset( $_POST['eloping_seo_form_update'] )
        && 'revert' === $_POST['eloping_seo_form_update']
        && check_admin_referer( 'eloping_seo_form_revert', 'eloping_seo_form_revert_nonce' ) ) {
        $restored = elfu_run_revert();
        set_transient(
            ELFU_NOTICE_TRANSIENT,
            array(
                'type'    => 'success',
                'message' => sprintf(
                    /* translators: %d: restored field-cells */
                    __( 'Reverted — %d form field(s) restored to their original value. Backup cleared.', 'eloping-seo-form-updater' ),
                    (int) $restored
                ),
            ),
            120
        );
        wp_safe_redirect( admin_url( 'admin.php?page=eloping-seo-form-updater' ) );
        exit;
    }
}

add_action( 'admin_notices', 'elfu_admin_notice' );
function elfu_admin_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'toplevel_page_eloping-seo-form-updater' !== $screen->id ) {
        return;
    }
    $notice = get_transient( ELFU_NOTICE_TRANSIENT );
    if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
        return;
    }
    delete_transient( ELFU_NOTICE_TRANSIENT );
    $type = 'error' === $notice['type'] ? 'error' : 'success';
    printf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr( $type ),
        esc_html( $notice['message'] )
    );
}

function elfu_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view this page.', 'eloping-seo-form-updater' ) );
    }
    $status   = elfu_status();
    $backup   = get_option( ELFU_BACKUP_OPTION, null );
    $has_back = is_array( $backup ) && count( $backup ) > 0;
    ?>
    <div class="wrap elfu-wrap">
        <h1><?php esc_html_e( 'SEO Form Bulk Update', 'eloping-seo-form-updater' ); ?></h1>

        <div class="card elfu-card">
            <h2><?php esc_html_e( 'What this does', 'eloping-seo-form-updater' ); ?></h2>
            <ul class="elfu-list">
                <li><?php esc_html_e( 'Finds every page that uses the “SEO Landing” page template (the pages listed in your user-sitemap). Nothing else — no menu pages, no header/footer, no other page.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'In BOTH “Form Shortcode” fields of those pages (the hero banner form and the bottom CTA band form), replaces the Contact Form 7 shortcode with the exact same Studio Ninja iframe embed that the Contact Us page uses — so the form stays consistent on every SEO landing page.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'Fields that already show the iframe form (updated manually) are left untouched. No other ACF field, no theme file, no menu, no other page is modified — design and layout stay exactly as they are.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'A full backup of the original values is saved first — the Revert button restores everything with one click.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'If you use a page-cache plugin (WP Rocket, W3TC, LiteSpeed, etc.), clear/regenerate the cache after running so front-end visitors see the new forms.', 'eloping-seo-form-updater' ); ?></li>
            </ul>

            <table class="widefat striped elfu-status">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Form field', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Already iframe form', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Still CF7 shortcode', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Empty', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Will be updated', 'eloping-seo-form-updater' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $status['fields'] as $field => $counts ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $counts['label'] ); ?></strong><br>
                            <code><?php echo esc_html( $field ); ?></code></td>
                        <td><?php echo esc_html( number_format_i18n( $counts['iframe'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $counts['cf7'] ) ); ?></td>
                        <td><?php
                            $e_parts = array();
                            if ( (int) $counts['empty'] > 0 ) {
                                $e_parts[] = number_format_i18n( (int) $counts['empty'] );
                            }
                            if ( (int) $counts['other'] > 0 ) {
                                $e_parts[] = number_format_i18n( (int) $counts['other'] ) . ' other';
                            }
                            echo esc_html( $e_parts ? implode( ' + ', $e_parts ) : '0' );
                        ?></td>
                        <td><strong><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                    <tr>
                        <td><strong><?php echo esc_html( sprintf(
                            /* translators: %d: page count */
                            __( '%d SEO landing page(s) in total', 'eloping-seo-form-updater' ),
                            (int) $status['total_pages']
                        ) ); ?></strong></td>
                        <td colspan="3"></td>
                        <td><strong><?php echo esc_html( number_format_i18n( $status['pending_total'] ) ); ?></strong> <?php esc_html_e( 'field(s)', 'eloping-seo-form-updater' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ( $status['pending_total'] > 0 ) : ?>
            <form method="post" action="" class="elfu-actions">
                <?php wp_nonce_field( 'eloping_seo_form_update', 'eloping_seo_form_update_nonce' ); ?>
                <input type="hidden" name="eloping_seo_form_update" value="run" />
                <button type="submit" class="button button-primary"
                    onclick="return confirm('<?php echo esc_js( sprintf( __( 'Update the hero + CTA form fields on %s SEO landing page(s) with the Contact-page iframe form? This can be undone with the Revert button.', 'eloping-seo-form-updater' ), number_format_i18n( $status['total_pages'] ) ) ); ?>');">
                    <?php esc_html_e( 'Run Update Now', 'eloping-seo-form-updater' ); ?>
                </button>
            </form>
            <?php else : ?>
            <p class="elfu-ok">&#10003; <?php esc_html_e( 'All form fields on all SEO landing pages already have the Contact-page iframe form — nothing to do.', 'eloping-seo-form-updater' ); ?></p>
            <?php endif; ?>

            <?php if ( $has_back ) : ?>
            <form method="post" action="" class="elfu-actions">
                <?php wp_nonce_field( 'eloping_seo_form_revert', 'eloping_seo_form_revert_nonce' ); ?>
                <input type="hidden" name="eloping_seo_form_update" value="revert" />
                <button type="submit" class="button"
                    onclick="return confirm('<?php echo esc_js( __( 'Revert ALL SEO landing pages back to their original form field values? The backup will be cleared afterwards.', 'eloping-seo-form-updater' ) ); ?>');">
                    <?php esc_html_e( 'Revert to Originals', 'eloping-seo-form-updater' ); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card elfu-card">
            <h2><?php esc_html_e( 'The exact code that will be written into the “Form Shortcode” fields', 'eloping-seo-form-updater' ); ?></h2>
            <p><?php esc_html_e( 'Identical to the Contact Us page’s Studio Ninja iframe embed (same form, same consistency on hero + CTA):', 'eloping-seo-form-updater' ); ?></p>
            <pre class="elfu-code"><?php echo esc_html( elfu_target_iframe_code() ); ?></pre>
        </div>
    </div>
    <style>
        .elfu-wrap .elfu-card{max-width:980px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px 20px 20px;margin-top:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);}
        .elfu-wrap .elfu-status{max-width:820px;margin-top:14px;}
        .elfu-wrap .elfu-list{margin:6px 0 4px 18px;line-height:1.7;}
        .elfu-wrap .elfu-actions{margin-top:18px;}
        .elfu-wrap .elfu-code{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:14px;font-size:12px;line-height:1.5;overflow:auto;}
        .elfu-wrap .elfu-ok{margin-top:16px;font-size:15px;color:#007017;}
    </style>
    <?php
}
