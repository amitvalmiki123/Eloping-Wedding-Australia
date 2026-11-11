<?php
/**
 * Plugin Name: Eloping SEO Form Bulk Updater
 * Plugin URI:  https://elopingweddings.com.au/
 * Description: One-click bulk update of the "Form Shortcode" fields (Hero + CTA) on every page that uses the SEO Landing template. Replaces the Contact Form 7 shortcode with the Studio Ninja iframe embed used on the Contact Us page (the CTA instance carries a distinct internal iframe id so the two iframes on a page never collide). Touches ONLY those two fields on those pages — no menus, no other pages, no other fields, no theme changes.
 * Version:     1.2.0
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
 * The two "Form Shortcode" fields (ACF) on the SEO Landing template
 * (registered in the child theme, inc/seo-landing-acf.php).
 *
 * WHY DIFFERENT IDS: the Studio Ninja embed ships with iFrameSizer, which
 * tracks iframes BY THEIR DOM `id` attribute. A page carries BOTH the hero
 * and the CTA embed, so the two instances must have different internal ids
 * or the resizer resizes the wrong iframe and the form gets cut off. The
 * `src` (and therefore the actual form, its fields and its submission) is
 * IDENTICAL for both.
 * ========================================================================= */

/**
 * Form-field spec: label + the internal iframe id this field's embed uses.
 * Filterable if you ever want to narrow or widen the set.
 */
function elfu_form_fields() {
    $fields = array(
        'seo_hero_form' => array(
            'label' => __( 'Hero banner — "Form Shortcode" field', 'eloping-seo-form-updater' ),
            'id'    => 'sn-form-m1co4',
        ),
        'seo_cta_form'  => array(
            'label' => __( 'Bottom CTA band — "Form Shortcode" field', 'eloping-seo-form-updater' ),
            'id'    => 'sn-form-m1co4-cta',
        ),
    );
    return apply_filters( 'eloping_seo_form_fields', $fields );
}

/* =========================================================================
 * TARGET VALUE — the iframe embed used on the Contact Us page
 * (source: the contact page's "contact_form_iframe" ACF field), with a
 * per-field internal id (see elfu_form_fields()).
 * ========================================================================= */

/**
 * The part of the src that identifies this exact Studio Ninja form
 * (used to tell "the same form, different id" from "a different form").
 */
function elfu_form_identifier() {
    return 'app.studioninja.co/contactform/parser/0a800fc9-a1a6-1d6c-81a1-c80ad5b35d26/0a800fc9-a1a6-1d6c-81a1-c80ad5d25d28';
}

/**
 * Build the embed code for a given internal iframe id.
 * Filterable if the Studio Ninja form ever changes.
 */
function elfu_target_iframe_code( $id ) {
    $code = <<<HTML
<iframe
    height="693"
    style="min-width: 100%; max-width: 600px; border: 0;"
    id="__ELFU_ID__"
    src="https://app.studioninja.co/contactform/parser/0a800fc9-a1a6-1d6c-81a1-c80ad5b35d26/0a800fc9-a1a6-1d6c-81a1-c80ad5d25d28"
    allowfullscreen>
</iframe>

<script
    type="text/javascript"
    data-iframe-id="__ELFU_ID__"
    src="https://app.studioninja.co/client-assets/form-render/assets/scripts/iframeResizer.js">
</script>
HTML;
    $code = str_replace( '__ELFU_ID__', $id, $code );
    return apply_filters( 'eloping_seo_form_iframe_code', $code, $id );
}

/**
 * True when a saved value is some Studio Ninja iframe embed.
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
 *     'total_pages'   => int,
 *     'fields'        => array( field => array(
 *        'label','correct','fix','other_iframe','empty','pending' ) ),
 *     'pending_total' => int,
 *   )
 *
 *   correct      — already exactly this field's target code
 *   fix          — CF7 / empty / other / same form with the wrong internal
 *                  id — will be (re)written
 *   other_iframe — a DIFFERENT Studio Ninja form — intentionally skipped
 */
function elfu_status() {
    $fields   = elfu_form_fields();
    $pages    = elfu_seo_landing_page_ids();
    $result   = array(
        'total_pages'   => count( $pages ),
        'fields'        => array(),
        'pending_total' => 0,
    );

    foreach ( $fields as $field => $spec ) {
        $target = elfu_target_iframe_code( $spec['id'] );
        $counts = array(
            'label'        => $spec['label'],
            'correct'      => 0,
            'fix'          => 0,
            'other_iframe' => 0,
            'empty'        => 0,
            'pending'      => 0,
        );
        foreach ( $pages as $pid ) {
            $key     = elfu_field_value_key( $pid, $field );
            $current = (string) get_post_meta( $pid, $key, true );
            if ( trim( $current ) === trim( $target ) ) {
                $counts['correct']++;
            } elseif ( elfu_is_iframe_value( $current )
                && false === strpos( $current, elfu_form_identifier() ) ) {
                // A different Studio Ninja form — respect it, skip.
                $counts['other_iframe']++;
            } else {
                $counts['fix']++;
                $counts['pending']++;
                if ( '' === trim( $current ) ) {
                    $counts['empty']++;
                }
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
 * Write this field's embed code into every SEO landing page that does not
 * already have exactly that code.
 *
 * Rules per field cell:
 *  - value == target (whitespace-insensitive)          -> skip
 *  - a DIFFERENT Studio Ninja form (different src)     -> skip (intentional)
 *  - the SAME form but different id / whitespace / CF7 -> write target
 *
 * Safety:
 *  - Only pages with _wp_page_template = template-seo-landing.php.
 *  - Only the listed "Form Shortcode" fields.
 *  - Every value that IS changed gets a one-time backup (earliest original
 *    kept) so the Revert button restores the originals exactly.
 *
 * Backup shape: option => array( pid => array( field => array(
 *     'key','value','existed','ref_added','ts' ) ) )
 */
function elfu_run_update() {
    $fields   = elfu_form_fields();
    $pages    = elfu_seo_landing_page_ids();
    $backup   = get_option( ELFU_BACKUP_OPTION, array() );
    if ( ! is_array( $backup ) ) {
        $backup = array();
    }

    $updated = 0;
    $skipped = 0;

    foreach ( $pages as $pid ) {
        foreach ( $fields as $field => $spec ) {
            $target  = elfu_target_iframe_code( $spec['id'] );
            $key     = elfu_field_value_key( $pid, $field );
            $current = (string) get_post_meta( $pid, $key, true );

            // Already exactly this field's target — skip.
            if ( trim( $current ) === trim( $target ) ) {
                $skipped++;
                continue;
            }

            // A different Studio Ninja form — skip (intentional custom form).
            if ( elfu_is_iframe_value( $current )
                && false === strpos( $current, elfu_form_identifier() ) ) {
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
                    /* translators: 1: updated field count, 2: skipped field count, 3: page count */
                    __( 'Done — %1$d form field(s) updated with the Contact-page iframe form across %3$d SEO landing page(s). %2$d field(s) were already correct and were skipped. A rollback backup was saved; use the Revert button on this screen to undo.', 'eloping-seo-form-updater' ),
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
                    /* translators: %d: restored field count */
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
                <li><?php esc_html_e( 'In BOTH “Form Shortcode” fields of those pages (the hero banner form and the bottom CTA band form), writes the exact same Studio Ninja form that the Contact Us page uses — same form, same fields, same submission destination, so the form stays consistent everywhere.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'The two iframes on a page carry different internal ids (the CTA uses “sn-form-m1co4-cta”) — this is required, because the embed’s auto-height script tracks iframes by id; identical ids make it resize the wrong iframe and the form gets cut off.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'Fields that already hold the correct code are left untouched. A different Studio Ninja form (different src) would be respected and skipped. No other ACF field, no theme file, no menu, no other page is modified — design and layout stay exactly as they are.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'A full backup of the original values is saved first — the Revert button restores everything with one click.', 'eloping-seo-form-updater' ); ?></li>
                <li><?php esc_html_e( 'If you use a page-cache plugin (WP Rocket, W3TC, LiteSpeed, etc.), clear/regenerate the cache after running so front-end visitors see the new forms.', 'eloping-seo-form-updater' ); ?></li>
            </ul>

            <table class="widefat striped elfu-status">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Form field', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Already correct', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Will be updated', 'eloping-seo-form-updater' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Different SN form (skipped)', 'eloping-seo-form-updater' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $status['fields'] as $field => $counts ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $counts['label'] ); ?></strong><br>
                            <code><?php echo esc_html( $field ); ?></code></td>
                        <td><?php echo esc_html( number_format_i18n( $counts['correct'] ) ); ?></td>
                        <td><strong><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></strong><?php if ( $counts['empty'] > 0 ) : ?> (<?php echo esc_html( number_format_i18n( $counts['empty'] ) ); ?> <?php esc_html_e( 'empty', 'eloping-seo-form-updater' ); ?>)<?php endif; ?></td>
                        <td><?php echo esc_html( number_format_i18n( $counts['other_iframe'] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr>
                        <td><strong><?php echo esc_html( sprintf(
                            /* translators: %d: page count */
                            __( '%d SEO landing page(s) in total', 'eloping-seo-form-updater' ),
                            (int) $status['total_pages']
                        ) ); ?></strong></td>
                        <td></td>
                        <td><strong><?php echo esc_html( number_format_i18n( $status['pending_total'] ) ); ?></strong> <?php esc_html_e( 'field(s)', 'eloping-seo-form-updater' ); ?></td>
                        <td></td>
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
            <p class="elfu-ok">&#10003; <?php esc_html_e( 'All form fields on all SEO landing pages already have the correct Contact-page iframe form — nothing to do.', 'eloping-seo-form-updater' ); ?></p>
            <?php endif; ?>

            <?php if ( $has_back ) : ?>
            <form method="post" action="" class="elfu-actions">
                <?php wp_nonce_field( 'eloping_seo_form_revert', 'eloping_seo_form_revert_nonce' ); ?>
                <input type="hidden" name="eloping_seo_form_update" value="revert" />
                <button type="submit" class="button"
                    onclick="return confirm('<?php echo esc_js( __( 'Revert ALL SEO landing pages back to their original form field values (Contact Form 7)? The backup will be cleared afterwards.', 'eloping-seo-form-updater' ) ); ?>');">
                    <?php esc_html_e( 'Revert to Originals', 'eloping-seo-form-updater' ); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card elfu-card">
            <h2><?php esc_html_e( 'The exact code that will be written into the “Form Shortcode” fields', 'eloping-seo-form-updater' ); ?></h2>
            <?php foreach ( elfu_form_fields() as $field => $spec ) : ?>
                <p><strong><?php echo esc_html( $spec['label'] ); ?></strong> <code><?php echo esc_html( $field ); ?></code>
                    <?php if ( 'sn-form-m1co4' !== $spec['id'] ) : ?>
                    — <?php echo esc_html( sprintf(
                        /* translators: %s: iframe id */
                        __( 'same form, internal id “%s” to avoid colliding with the hero iframe', 'eloping-seo-form-updater' ),
                        $spec['id']
                    ) ); ?>
                    <?php endif; ?>
                </p>
                <pre class="elfu-code"><?php echo esc_html( elfu_target_iframe_code( $spec['id'] ) ); ?></pre>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
        .elfu-wrap .elfu-card{max-width:980px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px 20px 20px;margin-top:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);}
        .elfu-wrap .elfu-status{max-width:820px;margin-top:14px;}
        .elfu-wrap .elfu-list{margin:6px 0 4px 18px;line-height:1.7;}
        .elfu-wrap .elfu-actions{margin-top:18px;}
        .elfu-wrap .elfu-code{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:14px;font-size:12px;line-height:1.5;overflow:auto;margin-bottom:18px;}
        .elfu-wrap .elfu-ok{margin-top:16px;font-size:15px;color:#007017;}
    </style>
    <?php
}
