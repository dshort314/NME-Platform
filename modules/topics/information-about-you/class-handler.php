<?php
/**
 * Information About You Handler
 * 
 * Processes Form 70 submissions:
 * - Creates Form 75 (Master) entry
 * - Maps fields from Form 70 to Form 75
 * - Sets user meta (anumber, parent_entry_id, dob)
 * - Calculates eligibility dates server-side
 * - Handles GravityView edit updates
 * 
 * CRITICAL: Date calculations in this file are the result of 18 months of work.
 * Do not alter, "improve," or infer anything in these calculations.
 */

namespace NME\Topics\InformationAboutYou;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Source form ID (Information About You) */
    const FORM_ID = 70;

    /** @var int Target form ID (Master) */
    const TARGET_FORM_ID = 75;

    /**
     * Fields that cannot be updated via GravityView edits for security/integrity reasons.
     * These are locked after initial submission to prevent data corruption.
     */
    const PROTECTED_FIELDS = [
        '5',   // DOB
        '10',  // A-Number
        '23',  // LPR Date
    ];

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Hook into pre-submission to create Master form entry BEFORE confirmation processing
        add_filter(
            'gform_pre_submission_filter_' . self::FORM_ID,
            [__CLASS__, 'create_master_entry_pre_submission']
        );

        // DISABLED: PHP backup calculations removed - JavaScript is authoritative
        // The date calculations and eligibility logic MUST run client-side via JavaScript.
        // PHP cannot accurately replicate this logic as it depends on user interactions
        // and Today's date which is set dynamically.
        // 
        // add_action(
        //     'gform_after_submission_' . self::FORM_ID,
        //     [__CLASS__, 'update_form_70_with_calculated_values'],
        //     5,
        //     2
        // );

        // Hook into confirmation to modify redirect URL
        add_filter(
            'gform_confirmation_' . self::FORM_ID,
            [__CLASS__, 'modify_confirmation_redirect'],
            10,
            4
        );

        // Hook into after submission to update user meta
        add_action(
            'gform_after_submission_' . self::FORM_ID,
            [__CLASS__, 'update_user_meta_after_submission'],
            10,
            2
        );

        // Hook into GravityView entry updates (edited entries)
        add_action(
            'gravityview/edit_entry/after_update',
            [__CLASS__, 'handle_gravityview_update'],
            10,
            3
        );
    }

    /**
     * Calculate and save date fields server-side
     * 
     * Since page 2 is skipped, JavaScript calculations never run and values aren't submitted.
     * We need to calculate everything server-side based on the LPR date from page 1.
     * 
     * CRITICAL: Do not alter these calculations.
     * 
     * @param array $entry The Form 70 entry
     * @param array $form The Form 70 form object
     */
    public static function update_form_70_with_calculated_values(array $entry, array $form): void {
        $entry_id = $entry['id'];

        // Get the LPR date from field 23
        $lpr_date = isset($entry['23']) ? $entry['23'] : '';
        $dm_date = isset($entry['18']) ? $entry['18'] : '';
        $sc_date = isset($entry['17']) ? $entry['17'] : '';

        if (empty($lpr_date)) {
            error_log('NME Platform - No LPR date found in entry ' . $entry_id . ', cannot calculate dates');
            return;
        }

        error_log('NME Platform - Calculating dates for entry ' . $entry_id . ' with LPR date: ' . $lpr_date);

        // Parse LPR date
        $lpr = \DateTime::createFromFormat('m/d/Y', $lpr_date);
        if (!$lpr) $lpr = \DateTime::createFromFormat('Y-m-d', $lpr_date);
        if (!$lpr) $lpr = \DateTime::createFromFormat('m-d-Y', $lpr_date);

        if (!$lpr) {
            error_log('NME Platform - Could not parse LPR date: ' . $lpr_date);
            return;
        }

        // Calculate and save LPR-based dates
        $lpr2 = clone $lpr;
        $lpr2->add(new \DateInterval('P2Y'))->sub(new \DateInterval('P90D'));
        \GFAPI::update_entry_field($entry_id, '25', $lpr2->format('m/d/Y'));

        $lpr3 = clone $lpr;
        $lpr3->add(new \DateInterval('P3Y'))->sub(new \DateInterval('P90D'));
        \GFAPI::update_entry_field($entry_id, '28', $lpr3->format('m/d/Y'));

        $lpr4 = clone $lpr;
        $lpr4->add(new \DateInterval('P4Y'))->sub(new \DateInterval('P90D'));
        \GFAPI::update_entry_field($entry_id, '27', $lpr4->format('m/d/Y'));

        $lprc = clone $lpr;
        $lprc->add(new \DateInterval('P5Y'))->sub(new \DateInterval('P90D'));
        \GFAPI::update_entry_field($entry_id, '26', $lprc->format('m/d/Y'));

        error_log('NME Platform - Saved LPR dates: LPR2=' . $lpr2->format('m/d/Y') . ', LPR3=' . $lpr3->format('m/d/Y') . ', LPR4=' . $lpr4->format('m/d/Y') . ', LPRC=' . $lprc->format('m/d/Y'));

        // Initialize calculated_dates array for Master form sync
        $calculated_dates = [
            '25' => '898',  // Form 70 field 25 -> Form 75 field 898 (LPR+2)
            '28' => '900',  // Form 70 field 28 -> Form 75 field 900 (LPR+3)
            '27' => '899',  // Form 70 field 27 -> Form 75 field 899 (LPR+4)
            '26' => '901',  // Form 70 field 26 -> Form 75 field 901 (LPRC)
        ];

        // Calculate and save DM-based dates if marriage date exists
        if (!empty($dm_date)) {
            $dm = \DateTime::createFromFormat('m/d/Y', $dm_date);
            if (!$dm) $dm = \DateTime::createFromFormat('Y-m-d', $dm_date);
            if (!$dm) $dm = \DateTime::createFromFormat('m-d-Y', $dm_date);

            if ($dm) {
                $dm2 = clone $dm;
                $dm2->add(new \DateInterval('P2Y'));
                \GFAPI::update_entry_field($entry_id, '32', $dm2->format('m/d/Y'));
                $calculated_dates['32'] = '904';  // Form 70 field 32 -> Form 75 field 904

                $dmc = clone $dm;
                $dmc->add(new \DateInterval('P3Y'));
                \GFAPI::update_entry_field($entry_id, '31', $dmc->format('m/d/Y'));
                $calculated_dates['31'] = '903';  // Form 70 field 31 -> Form 75 field 903

                error_log('NME Platform - Saved DM dates: DM2=' . $dm2->format('m/d/Y') . ', DMC=' . $dmc->format('m/d/Y'));
            }
        }

        // Calculate and save SC-based dates if spouse citizenship date exists
        if (!empty($sc_date)) {
            $sc = \DateTime::createFromFormat('m/d/Y', $sc_date);
            if (!$sc) $sc = \DateTime::createFromFormat('Y-m-d', $sc_date);
            if (!$sc) $sc = \DateTime::createFromFormat('m-d-Y', $sc_date);

            if ($sc) {
                $sc2 = clone $sc;
                $sc2->add(new \DateInterval('P2Y'));
                \GFAPI::update_entry_field($entry_id, '30', $sc2->format('m/d/Y'));
                $calculated_dates['30'] = '905';  // Form 70 field 30 -> Form 75 field 905

                $scc = clone $sc;
                $scc->add(new \DateInterval('P3Y'));
                \GFAPI::update_entry_field($entry_id, '29', $scc->format('m/d/Y'));
                $calculated_dates['29'] = '902';  // Form 70 field 29 -> Form 75 field 902

                error_log('NME Platform - Saved SC dates: SC2=' . $sc2->format('m/d/Y') . ', SCC=' . $scc->format('m/d/Y'));
            }
        }

        // NOW update the Master form entry (Form 75) with all calculated dates
        // AND update Form 70 with the correct eligibility values from the Master form
        $master_entry_id = isset($entry['50']) ? $entry['50'] : '';

        if (!empty($master_entry_id)) {
            // Get the Master form entry which has the correct eligibility calculations
            $master_entry = \GFAPI::get_entry($master_entry_id);

            if (!is_wp_error($master_entry)) {
                // First, copy the CORRECT eligibility values from Master to Form 70
                if (isset($master_entry['894']) && !empty($master_entry['894'])) {
                    \GFAPI::update_entry_field($entry_id, '34', $master_entry['894']);  // Controlling Factor
                }
                if (isset($master_entry['895']) && !empty($master_entry['895'])) {
                    \GFAPI::update_entry_field($entry_id, '35', $master_entry['895']);  // Application Date
                }
                if (isset($master_entry['896']) && !empty($master_entry['896'])) {
                    \GFAPI::update_entry_field($entry_id, '36', $master_entry['896']);  // Application Date Description
                }
                if (isset($master_entry['897']) && !empty($master_entry['897'])) {
                    \GFAPI::update_entry_field($entry_id, '37', $master_entry['897']);  // Status
                }

                error_log('NME Platform - Copied correct eligibility values from Master to Form 70');

                // Now get the updated Form 70 entry with all calculated date values
                $updated_entry = \GFAPI::get_entry($entry_id);

                if (!is_wp_error($updated_entry)) {
                    // Update Master form with all calculated date fields
                    foreach ($calculated_dates as $form_70_field => $form_75_field) {
                        if (isset($updated_entry[$form_70_field]) && !empty($updated_entry[$form_70_field])) {
                            \GFAPI::update_entry_field($master_entry_id, $form_75_field, $updated_entry[$form_70_field]);
                            error_log('NME Platform - Updated Master form field ' . $form_75_field . ' with value: ' . $updated_entry[$form_70_field]);
                        }
                    }

                    error_log('NME Platform - Updated Master form entry ' . $master_entry_id . ' with calculated dates');
                } else {
                    error_log('NME Platform - Could not retrieve updated Form 70 entry ' . $entry_id);
                }
            } else {
                error_log('NME Platform - Could not retrieve Master form entry ' . $master_entry_id);
            }
        } else {
            error_log('NME Platform - No Master form entry ID found in field 50');
        }
    }

    /**
     * Handle GravityView entry updates
     * 
     * @param array $form The form object
     * @param int|string $entry_id The entry ID that was updated
     * @param mixed $entry The updated entry object
     */
    public static function handle_gravityview_update(array $form, $entry_id, $entry): void {
        // Only process if this is a Form 70 (Information About You) update
        if ((int) $form['id'] !== self::FORM_ID) {
            return;
        }

        // Get the fresh entry data after update
        $updated_entry = \GFAPI::get_entry($entry_id);
        if (is_wp_error($updated_entry)) {
            error_log('NME Platform - Information About You Handler: Could not retrieve updated entry ' . $entry_id . ': ' . $updated_entry->get_error_message());
            return;
        }

        // Update the linked Master form entry with non-protected fields only
        self::update_master_from_gravityview_edit($updated_entry, $form);
    }

    /**
     * Update Master form entry from GravityView edit (excludes protected fields)
     * 
     * @param array $entry The updated Form 70 entry data
     * @param array $form The Form 70 form object
     */
    private static function update_master_from_gravityview_edit(array $entry, array $form): void {
        try {
            // Get the Master form entry ID from field 50 in Information About You form
            $master_entry_id = isset($entry['50']) ? $entry['50'] : '';

            if (empty($master_entry_id)) {
                error_log('NME Platform - Information About You Handler: No Master form entry ID found in Information About You field 50. Cannot sync updates.');
                return;
            }

            // Verify the Master form entry exists
            $master_entry = \GFAPI::get_entry($master_entry_id);
            if (is_wp_error($master_entry)) {
                error_log('NME Platform - Information About You Handler: Could not find Master form entry with ID ' . $master_entry_id . ': ' . $master_entry->get_error_message());
                return;
            }

            // Get field mappings (excluding protected fields)
            $field_mappings = self::get_field_mappings();
            $updates_made = 0;
            $update_details = [];
            $protected_skipped = [];
            $errors = [];

            // Update each field in Master form (excluding protected fields)
            foreach ($field_mappings as $source_field => $mapping) {
                $target_field = $mapping['target_field'];
                $field_description = $mapping['description'];

                // Skip protected fields during GravityView updates
                if (in_array($source_field, self::PROTECTED_FIELDS)) {
                    $protected_skipped[] = $field_description;
                    continue;
                }

                // Handle complex field mappings (like name components)
                if (strpos($source_field, '.') !== false) {
                    // Complex field (e.g., "1.3" for first name)
                    if (isset($entry[$source_field]) && !empty($entry[$source_field])) {
                        $update_result = \GFAPI::update_entry_field($master_entry_id, $target_field, $entry[$source_field]);

                        if (is_wp_error($update_result)) {
                            $error_msg = 'Failed to update ' . $field_description . ' (Master field ' . $target_field . '): ' . $update_result->get_error_message();
                            error_log('NME Platform - Information About You Handler: ' . $error_msg);
                            $errors[] = $error_msg;
                        } else {
                            $updates_made++;
                            $update_details[] = $field_description . ': "' . $entry[$source_field] . '"';
                        }
                    }
                } else {
                    // Simple field mapping
                    if (isset($entry[$source_field]) && !empty($entry[$source_field])) {
                        $update_result = \GFAPI::update_entry_field($master_entry_id, $target_field, $entry[$source_field]);

                        if (is_wp_error($update_result)) {
                            $error_msg = 'Failed to update ' . $field_description . ' (Master field ' . $target_field . '): ' . $update_result->get_error_message();
                            error_log('NME Platform - Information About You Handler: ' . $error_msg);
                            $errors[] = $error_msg;
                        } else {
                            $updates_made++;
                            $update_details[] = $field_description . ': "' . $entry[$source_field] . '"';
                        }
                    }
                }
            }

            // Log results
            $debug_mode = Plugin::is_debug_enabled('information-about-you');

            if ($updates_made > 0 && $debug_mode) {
                $summary = 'Successfully synchronized ' . $updates_made . ' information fields to Master entry ' . $master_entry_id;
                if (!empty($update_details)) {
                    $summary .= '. Updates: ' . implode(', ', $update_details);
                }
                if (!empty($protected_skipped)) {
                    $summary .= '. Protected fields skipped: ' . implode(', ', $protected_skipped);
                }
                error_log('NME Platform - Information About You Handler: ' . $summary);
            }

            // Always log errors
            if (!empty($errors)) {
                error_log('NME Platform - Information About You Handler: ' . count($errors) . ' errors occurred while synchronizing information to Master entry ' . $master_entry_id);
            }

        } catch (\Exception $e) {
            error_log('NME Platform - Information About You Handler: Exception occurred while processing GravityView update: ' . $e->getMessage());
        }
    }

    /**
     * Create Master form entry during initial submission (includes all fields)
     * 
     * @param array $form The form object
     * @return array The form object
     */
    public static function create_master_entry_pre_submission(array $form): array {
        try {
            // Get the current entry data from $_POST
            $entry_data = \GFFormsModel::create_lead($form);

            // Check if this is an update (field 50 already has a value)
            if (!empty($entry_data['50'])) {
                return $form;  // Already processed, don't create duplicate
            }

            // Calculate and assign eligibility values to fields 34, 35, 36, and 37 if they are empty
            self::calculate_and_assign_eligibility_fields($entry_data);

            // Get the target form
            $target_form = \GFAPI::get_form(self::TARGET_FORM_ID);
            if (is_wp_error($target_form)) {
                error_log('NME Platform - Information About You Handler: Could not get Master form ' . self::TARGET_FORM_ID . ': ' . $target_form->get_error_message());
                return $form;
            }

            // Map fields from Information About You form to Master form
            $new_entry = self::map_fields_for_creation($entry_data);

            // Create the new entry in Master form
            $new_entry_id = \GFAPI::add_entry($new_entry);

            if (is_wp_error($new_entry_id)) {
                error_log('NME Platform - Information About You Handler: Could not create Master form entry: ' . $new_entry_id->get_error_message());
                return $form;
            }

            // Update the Master form entry with its own ID in field 892 (Parent ID)
            $update_result = \GFAPI::update_entry_field($new_entry_id, '892', $new_entry_id);

            if (is_wp_error($update_result)) {
                error_log('NME Platform - Information About You Handler: Could not update Master form entry field 892: ' . $update_result->get_error_message());
            }

            // Store the Master form entry ID in Information About You form field 50 by modifying $_POST
            $_POST['input_50'] = $new_entry_id;

            $debug_mode = Plugin::is_debug_enabled('information-about-you');
            if ($debug_mode) {
                error_log('NME Platform - Information About You Handler: Successfully created Master form entry ' . $new_entry_id . ' and linked to Information About You form');
            }

        } catch (\Exception $e) {
            error_log('NME Platform - Information About You Handler: Exception occurred during Master form creation: ' . $e->getMessage());
        }

        return $form;
    }

    /**
     * Calculate and assign eligibility determination fields
     * 
     * CRITICAL: Do not alter these calculations.
     * 
     * @param array $entry_data Entry data (passed by reference)
     */
    private static function calculate_and_assign_eligibility_fields(array &$entry_data): void {
        try {
            // Check if eligibility fields 34, 35, 36, and 37 are empty
            if (empty($entry_data['34']) && empty($entry_data['35']) && empty($entry_data['36']) && empty($entry_data['37'])) {
                // Assign default values for eligibility determination
                $entry_data['34'] = 'LPR';           // Controlling Factor
                $entry_data['36'] = 'LPRC - 1A';     // Application Date Description
                $entry_data['37'] = 'Eligible Now';  // Status
                $entry_data['20'] = 'LPR';           // Reason for Filing

                // Calculate eligibility date (field 35) based on LPR date (field 23)
                if (!empty($entry_data['23'])) {
                    $lpr_date = $entry_data['23'];

                    // Parse the date in various formats
                    $date = \DateTime::createFromFormat('Y-m-d', $lpr_date);
                    if (!$date) $date = \DateTime::createFromFormat('m/d/Y', $lpr_date);
                    if (!$date) $date = \DateTime::createFromFormat('d/m/Y', $lpr_date);
                    if (!$date) $date = \DateTime::createFromFormat('Y-m-d H:i:s', $lpr_date);

                    if ($date) {
                        // Add 5 years and subtract 90 days (standard eligibility calculation)
                        $date->add(new \DateInterval('P5Y'));
                        $date->sub(new \DateInterval('P90D'));

                        // Format the result as YYYY-MM-DD
                        $entry_data['35'] = $date->format('Y-m-d');

                        $debug_mode = Plugin::is_debug_enabled('information-about-you');
                        if ($debug_mode) {
                            error_log('NME Platform - Information About You Handler: Calculated eligibility date as ' . $entry_data['35'] . ' from LPR date ' . $lpr_date);
                        }
                    } else {
                        error_log('NME Platform - Information About You Handler: Could not parse LPR date: ' . $lpr_date);
                    }
                }

                // Update $_POST to reflect these changes
                $_POST['input_34'] = $entry_data['34'];
                $_POST['input_35'] = $entry_data['35'];
                $_POST['input_36'] = $entry_data['36'];
                $_POST['input_37'] = $entry_data['37'];
                $_POST['input_20'] = $entry_data['20'];
            }
        } catch (\Exception $e) {
            error_log('NME Platform - Information About You Handler: Exception occurred in eligibility calculation: ' . $e->getMessage());
        }
    }

    /**
     * Modify confirmation redirect to include Master form entry ID
     * 
     * @param mixed $confirmation The confirmation
     * @param array $form The form object
     * @param array $entry The entry
     * @param bool $ajax Whether AJAX is enabled
     * @return mixed The confirmation
     */
    public static function modify_confirmation_redirect($confirmation, array $form, array $entry, bool $ajax) {
        // Only modify if it's a redirect confirmation and we have the Master form entry ID
        if (is_array($confirmation) && isset($confirmation['type']) && $confirmation['type'] == 'redirect') {
            $master_entry_id = $entry['50'];  // Get from field 50

            if (!empty($master_entry_id)) {
                $redirect_url = $confirmation['url'];

                // Add parent_entry_id parameter to the existing URL
                $separator = (strpos($redirect_url, '?') !== false) ? '&' : '?';
                $confirmation['url'] = $redirect_url . $separator . 'parent_entry_id=' . $master_entry_id;

                $debug_mode = Plugin::is_debug_enabled('information-about-you');
                if ($debug_mode) {
                    error_log('NME Platform - Information About You Handler: Modified redirect URL to include parent_entry_id=' . $master_entry_id);
                }
            }
        }

        return $confirmation;
    }

    /**
     * Update user meta after form submission
     * 
     * @param array $entry The entry
     * @param array $form The form object
     */
    public static function update_user_meta_after_submission(array $entry, array $form): void {
        $user_id = get_current_user_id();

        if ($user_id && !empty($entry['50'])) {
            // Update user meta with key information
            if (isset($entry['10']) && !empty($entry['10'])) {
                UserContext::set_anumber($entry['10'], $user_id);
            }

            if (isset($entry['5']) && !empty($entry['5'])) {
                UserContext::set_dob($entry['5'], $user_id);
            }

            UserContext::set_parent_entry_id((int) $entry['50'], $user_id);

            $debug_mode = Plugin::is_debug_enabled('information-about-you');
            if ($debug_mode) {
                error_log('NME Platform - Information About You Handler: Updated user meta for user ' . $user_id . ' with parent_entry_id=' . $entry['50']);
            }
        }
    }

    /**
     * Get field mappings for Information About You form (Form 70) to Master form (Form 75)
     * Used for GravityView updates (excludes protected fields during sync)
     * 
     * @return array Field mappings with descriptions
     */
    public static function get_field_mappings(): array {
        return [
            // Basic Information (protected fields marked but handled separately)
            '10' => ['target_field' => '1', 'description' => 'A-Number (PROTECTED)'],
            '5' => ['target_field' => '737', 'description' => 'Date of Birth (PROTECTED)'],
            '23' => ['target_field' => '738', 'description' => 'LPR Date (PROTECTED)'],

            // Safe to update fields
            '24' => ['target_field' => '32', 'description' => "Today's Date"],
            '20' => ['target_field' => '730', 'description' => 'Reason for Filing'],

            // Current Legal Name components
            '1.2' => ['target_field' => '159.2', 'description' => 'Name Prefix'],
            '1.3' => ['target_field' => '159.3', 'description' => 'First Name'],
            '1.4' => ['target_field' => '159.4', 'description' => 'Middle Name'],
            '1.6' => ['target_field' => '159.6', 'description' => 'Last Name'],
            '1.8' => ['target_field' => '159.8', 'description' => 'Name Suffix'],

            // Other Names
            '3' => ['target_field' => '731', 'description' => 'Used Other Names Since Birth'],
            '4' => ['target_field' => '732', 'description' => 'Other Names List'],

            // Personal Information
            '21' => ['target_field' => '739', 'description' => 'Country of Birth'],
            '22' => ['target_field' => '740', 'description' => 'Country of Citizenship'],

            // Marital Status
            '11' => ['target_field' => '758', 'description' => 'Current Marital Status'],
            '18' => ['target_field' => '764', 'description' => 'Date of Marriage to U.S. Citizen'],

            // Current Spouse's Legal Name components
            '14.2' => ['target_field' => '762.2', 'description' => 'Spouse Name Prefix'],
            '14.3' => ['target_field' => '762.3', 'description' => 'Spouse First Name'],
            '14.4' => ['target_field' => '762.4', 'description' => 'Spouse Middle Name'],
            '14.6' => ['target_field' => '762.6', 'description' => 'Spouse Last Name'],
            '14.8' => ['target_field' => '762.8', 'description' => 'Spouse Name Suffix'],

            // Spouse Information
            '15' => ['target_field' => '763', 'description' => 'Spouse Date of Birth'],
            '16' => ['target_field' => '766', 'description' => 'When Spouse Became U.S. Citizen'],
            '17' => ['target_field' => '767', 'description' => 'Date Spouse Became U.S. Citizen'],
            '19' => ['target_field' => '765', 'description' => 'Spouse Same Physical Address'],

            // Date Calculations (auto-calculated, but can be updated)
            '34' => ['target_field' => '894', 'description' => 'Controlling Factor'],
            '35' => ['target_field' => '895', 'description' => 'Application Date'],
            '36' => ['target_field' => '896', 'description' => 'Application Date Description'],
            '37' => ['target_field' => '897', 'description' => 'Eligibility Status'],
            '25' => ['target_field' => '898', 'description' => 'LPR+2 Date'],
            '28' => ['target_field' => '900', 'description' => 'LPR+3 Date'],
            '26' => ['target_field' => '901', 'description' => 'LPRC Date'],
            '27' => ['target_field' => '899', 'description' => 'LPR+4 Date'],
            '32' => ['target_field' => '904', 'description' => 'DM+2 Date'],
            '31' => ['target_field' => '903', 'description' => 'DMC Date'],
            '30' => ['target_field' => '905', 'description' => 'SC+2 Date'],
            '29' => ['target_field' => '902', 'description' => 'SCC Date'],
        ];
    }

    /**
     * Map fields for initial Master form creation (includes all fields)
     * 
     * @param array $source_entry Source entry data
     * @return array Mapped entry for Master form
     */
    private static function map_fields_for_creation(array $source_entry): array {
        $mapped_entry = [
            'form_id' => self::TARGET_FORM_ID,
            'date_created' => current_time('mysql'),
            'is_starred' => 0,
            'is_read' => 0,
            'ip' => \GFFormsModel::get_ip(),
            'source_url' => \GFFormsModel::get_current_page_url(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'currency' => 'USD',
            'payment_status' => null,
            'payment_date' => null,
            'transaction_id' => null,
            'payment_amount' => null,
            'payment_method' => null,
            'is_fulfilled' => null,
            'created_by' => get_current_user_id(),
            'transaction_type' => null,
            'status' => 'active',
        ];

        // Get all field mappings for initial creation
        $field_mappings = self::get_field_mappings();

        // Apply field mappings
        foreach ($field_mappings as $source_field => $mapping) {
            $target_field = $mapping['target_field'];

            if (isset($source_entry[$source_field]) && !empty($source_entry[$source_field])) {
                $mapped_entry[$target_field] = $source_entry[$source_field];
            }
        }

        return $mapped_entry;
    }

    /**
     * Get protected fields that cannot be edited via GravityView
     * 
     * @return array Protected field IDs
     */
    public static function get_protected_fields(): array {
        return self::PROTECTED_FIELDS;
    }
}
