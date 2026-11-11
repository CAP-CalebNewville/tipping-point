<?php
// Ajax handler for admin.php requests
// This file handles Ajax requests before any HTML output

// Handle the aircraft editing Ajax requests
if (isset($_REQUEST['what'])) {
    switch (($_REQUEST['what'] ?? null)) {
        case "cg":
            $envelope_name = ($_REQUEST['envelope_name'] ?? 'Normal');
            if (isset($_REQUEST['new_arm']) && ($_REQUEST['new_arm'] ?? null) != "" && isset($_REQUEST['new_weight']) && ($_REQUEST['new_weight'] ?? null) != "") {
                // Check if this envelope already exists to get its color, or use provided color, or use default
                $color_query = $db->query("SELECT DISTINCT color FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ? LIMIT 1", [($_REQUEST['tailnumber'] ?? null), $envelope_name]);
                $color_result = $db->fetchAssoc($color_query);
                
                // Use existing color if found, otherwise use provided envelope_color, otherwise default to blue
                if ($color_result) {
                    $envelope_color = $color_result['color'];
                } elseif (isset($_REQUEST['envelope_color']) && !empty($_REQUEST['envelope_color'])) {
                    $envelope_color = ($_REQUEST['envelope_color'] ?? null);
                } else {
                    $envelope_color = 'blue';
                }
                
                // SQL query to add a new CG line
                $sql_query = "INSERT INTO aircraft_cg (`id`, `tailnumber`, `arm`, `weight`, `envelope_name`, `color`) VALUES (NULL, ?, ?, ?, ?, ?)";
                $db->query($sql_query, [($_REQUEST['tailnumber'] ?? null), ($_REQUEST['new_arm'] ?? null), ($_REQUEST['new_weight'] ?? null), $envelope_name, $envelope_color]);
                
                // Get the ID of the newly inserted row
                $new_cg_id = $db->lastInsertId();
                
                // Fallback: If lastInsertId() failed, try to find the record we just inserted
                if (!$new_cg_id) {
                    $fallback_query = $db->query("SELECT id FROM aircraft_cg WHERE tailnumber = ? AND arm = ? AND weight = ? AND envelope_name = ? ORDER BY id DESC LIMIT 1", 
                        [($_REQUEST['tailnumber'] ?? null), ($_REQUEST['new_arm'] ?? null), ($_REQUEST['new_weight'] ?? null), $envelope_name]);
                    $fallback_result = $db->fetchAssoc($fallback_query);
                    if ($fallback_result) {
                        $new_cg_id = $fallback_result['id'];
                    }
                }
                
                // Enter in the audit log
                $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                $aircraft = $db->fetchAssoc($aircraft_query);
                $audit_data = ['arm' => ($_REQUEST['new_arm'] ?? null), 'weight' => ($_REQUEST['new_weight'] ?? null), 'envelope' => $envelope_name];
                $audit_message = createAuditMessage("Added CG envelope point", $audit_data);
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                
                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'id' => $new_cg_id,
                    'arm' => ($_REQUEST['new_arm'] ?? null),
                    'weight' => ($_REQUEST['new_weight'] ?? null),
                    'envelope_name' => $envelope_name
                ]);
            } elseif (isset($_REQUEST['id']) && isset($_REQUEST['cgarm']) && isset($_REQUEST['cgweight'])) {
                // Update existing CG point
                $sql_query = "UPDATE aircraft_cg SET arm = ?, weight = ? WHERE id = ?";
                $db->query($sql_query, [($_REQUEST['cgarm'] ?? null), ($_REQUEST['cgweight'] ?? null), ($_REQUEST['id'] ?? null)]);

                // Enter in the audit log
                $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                $aircraft = $db->fetchAssoc($aircraft_query);
                $audit_data = ['arm' => ($_REQUEST['cgarm'] ?? null), 'weight' => ($_REQUEST['cgweight'] ?? null), 'cg_id' => ($_REQUEST['id'] ?? null), 'envelope' => $envelope_name];
                $audit_message = createAuditMessage("Updated CG envelope point", $audit_data);
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);

                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'CG point updated successfully',
                    'id' => ($_REQUEST['id'] ?? null),
                    'arm' => ($_REQUEST['cgarm'] ?? null),
                    'weight' => ($_REQUEST['cgweight'] ?? null)
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Missing arm or weight values']);
            }
            break;
            
        case "cg_del":
            // Delete CG point
            if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
                $id = ($_REQUEST['id'] ?? null);
                
                // Get CG point info before deletion for audit log
                $cg_query = $db->query("SELECT * FROM aircraft_cg WHERE id = ?", [$id]);
                $cg_info = $db->fetchAssoc($cg_query);
                
                if ($cg_info) {
                    // Delete the CG point
                    $db->query("DELETE FROM aircraft_cg WHERE id = ?", [$id]);
                    
                    // Enter in the audit log
                    $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                    $aircraft = $db->fetchAssoc($aircraft_query);
                    $audit_data = [
                        'id' => $id,
                        'arm' => $cg_info['arm'],
                        'weight' => $cg_info['weight'],
                        'envelope' => $cg_info['envelope_name']
                    ];
                    $audit_message = createAuditMessage("Deleted CG envelope point", $audit_data);
                    $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                    
                    // Return JSON response
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'CG point deleted successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'CG point not found']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Missing CG point ID']);
            }
            break;
            
        case "envelope_add":
            // Add new envelope via AJAX
            $envelope_name = trim(($_REQUEST['envelope_name'] ?? ''));
            $envelope_color = ($_REQUEST['envelope_color'] ?? null);
            
            if (!empty($envelope_name)) {
                // Check if envelope name already exists
                $check_query = $db->query("SELECT COUNT(*) as count FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ?", [($_REQUEST['tailnumber'] ?? null), $envelope_name]);
                $check_result = $db->fetchAssoc($check_query);
                
                if ($check_result['count'] > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'An envelope with this name already exists']);
                    break;
                }
                
                // Check if color is already in use
                $color_check_query = $db->query("SELECT COUNT(*) as count FROM aircraft_cg WHERE tailnumber = ? AND color = ?", [($_REQUEST['tailnumber'] ?? null), $envelope_color]);
                $color_check_result = $db->fetchAssoc($color_check_query);
                
                if ($color_check_result['count'] > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'This color is already in use by another envelope']);
                    break;
                }
                
                // Enter in the audit log for new envelope creation
                $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                $aircraft = $db->fetchAssoc($aircraft_query);
                $audit_data = ['envelope_name' => $envelope_name, 'color' => $envelope_color];
                $audit_message = createAuditMessage("Created new CG envelope", $audit_data);
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                
                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'envelope_name' => $envelope_name,
                    'envelope_color' => $envelope_color
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Envelope name is required']);
            }
            break;
            
        case "loading":
            // Handle both new loading zones and updates to existing ones
            if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
                // This is an update to an existing loading zone
                $id = ($_REQUEST['id'] ?? null);
                $type = $_REQUEST['type'] ?? 'Variable Weight no limit';
                
                // Validate that only one Empty Weight type can exist per aircraft (for updates)
                if ($type === 'Empty Weight') {
                    $existing_empty_weight = $db->query("SELECT COUNT(*) as count FROM aircraft_weights WHERE tailnumber = ? AND type = 'Empty Weight' AND id != ?", [($_REQUEST['tailnumber'] ?? null), $id]);
                    $existing_count = $db->fetchAssoc($existing_empty_weight);
                    if ($existing_count['count'] > 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Empty Weight type can only exist once per aircraft']);
                        break;
                    }
                }
                
                // Handle weight_limit based on type
                if ($type === 'Variable Weight with limit' || $type === 'Fuel') {
                    $weight_limit = !empty($_REQUEST['weight_limit']) ? ($_REQUEST['weight_limit'] ?? null) : null;
                } elseif ($type === 'Fixed Weight Removable') {
                    $weight_limit = !empty($_REQUEST['weight_limit']) ? 1 : 0;
                } else {
                    $weight_limit = null;
                }
                
                // Update the existing loading zone
                $params = [($_REQUEST['order'] ?? null), ($_REQUEST['item'] ?? null), ($_REQUEST['weight'] ?? null), ($_REQUEST['arm'] ?? null), $type, $weight_limit, $id];
                $sql_query = "UPDATE aircraft_weights SET `order` = ?, `item` = ?, `weight` = ?, `arm` = ?, `type` = ?, `weight_limit` = ? WHERE `id` = ?";
                $db->query($sql_query, $params);
                
                // Enter in the audit log
                $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                $aircraft = $db->fetchAssoc($aircraft_query);
                $audit_data = [
                    'id' => $id,
                    'order' => ($_REQUEST['order'] ?? null),
                    'item' => ($_REQUEST['item'] ?? null),
                    'weight' => ($_REQUEST['weight'] ?? null),
                    'arm' => ($_REQUEST['arm'] ?? null),
                    'type' => $type
                ];
                if ($weight_limit !== null) {
                    $audit_data['weight_limit'] = $weight_limit;
                }
                $audit_message = createAuditMessage("Updated aircraft loading item", $audit_data);
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                
                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Loading zone updated successfully']);
                
            } elseif (isset($_REQUEST['new_item']) && ($_REQUEST['new_item'] ?? null) && isset($_REQUEST['new_arm']) && ($_REQUEST['new_arm'] ?? null) != "") {
                // Set order to highest existing order + 1 if not specified
                $order = ($_REQUEST['new_order'] ?? null);
                if (empty($order)) {
                    $max_order_query = $db->query("SELECT MAX(`order`) as max_order FROM aircraft_weights WHERE tailnumber = ?", [($_REQUEST['tailnumber'] ?? null)]);
                    $max_order_result = $db->fetchAssoc($max_order_query);
                    $order = ($max_order_result['max_order'] ?? 0) + 1;
                }
                
                // SQL query to add a new loading line with new type system
                $type = $_REQUEST['new_type'] ?? 'Variable Weight no limit';
                
                // Validate that only one Empty Weight type can exist per aircraft
                if ($type === 'Empty Weight') {
                    $existing_empty_weight = $db->query("SELECT COUNT(*) as count FROM aircraft_weights WHERE tailnumber = ? AND type = 'Empty Weight'", [($_REQUEST['tailnumber'] ?? null)]);
                    $existing_count = $db->fetchAssoc($existing_empty_weight);
                    if ($existing_count['count'] > 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Empty Weight type can only exist once per aircraft']);
                        break;
                    }
                }
                
                if ($type === 'Variable Weight with limit' || $type === 'Fuel') {
                    $weight_limit = !empty($_REQUEST['new_weight_limit']) ? ($_REQUEST['new_weight_limit'] ?? null) : null;
                } elseif ($type === 'Fixed Weight Removable') {
                    $weight_limit = !empty($_REQUEST['new_default_installed']) ? 1 : 0;
                } else {
                    $weight_limit = null;
                }
                
                // Validate weight against limit (only for Variable Weight with limit and Fuel types)
                if (($type === 'Variable Weight with limit' || $type === 'Fuel') && $weight_limit !== null && ($_REQUEST['new_weight'] ?? null) > $weight_limit) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Weight (' . ($_REQUEST['new_weight'] ?? null) . ') cannot exceed weight limit (' . $weight_limit . ')']);
                    break;
                }
                
                $params = [null, ($_REQUEST['tailnumber'] ?? null), $order, ($_REQUEST['new_item'] ?? null), ($_REQUEST['new_weight'] ?? null), ($_REQUEST['new_arm'] ?? null), 0, $type, $weight_limit];
                $sql_query = "INSERT INTO aircraft_weights (`id`, `tailnumber`, `order`, `item`, `weight`, `arm`, `fuelwt`, `type`, `weight_limit`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($sql_query, $params);
                
                // Get the ID of the newly inserted row
                $new_weight_id = $db->lastInsertId();
                
                // Enter in the audit log
                $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                $aircraft = $db->fetchAssoc($aircraft_query);
                $audit_data = [
                    'order' => $order,
                    'item' => ($_REQUEST['new_item'] ?? null),
                    'weight' => ($_REQUEST['new_weight'] ?? null),
                    'arm' => ($_REQUEST['new_arm'] ?? null),
                    'type' => $type
                ];
                if ($weight_limit !== null) {
                    $audit_data['weight_limit'] = $weight_limit;
                }
                $audit_message = createAuditMessage("Added aircraft loading item", $audit_data);
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                
                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'id' => $new_weight_id,
                    'order' => $order,
                    'item' => ($_REQUEST['new_item'] ?? null),
                    'weight' => ($_REQUEST['new_weight'] ?? null),
                    'arm' => ($_REQUEST['new_arm'] ?? null),
                    'type' => $type,
                    'weight_limit' => $weight_limit
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            }
            break;
            
        case "envelope_edit":
            // Edit existing envelope via AJAX
            $old_envelope_name = trim(($_REQUEST['old_envelope_name'] ?? ''));
            $envelope_name = trim(($_REQUEST['envelope_name'] ?? ''));
            $envelope_color = ($_REQUEST['envelope_color'] ?? null);
            
            if (!empty($old_envelope_name) && !empty($envelope_name)) {
                // Check if new envelope name already exists (and it's different from the current one)
                if ($old_envelope_name !== $envelope_name) {
                    $check_query = $db->query("SELECT COUNT(*) as count FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ?", [($_REQUEST['tailnumber'] ?? null), $envelope_name]);
                    $check_result = $db->fetchAssoc($check_query);
                    
                    if ($check_result['count'] > 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'An envelope with this name already exists']);
                        break;
                    }
                }
                
                // Check if color is already in use by another envelope
                $color_check_query = $db->query("SELECT COUNT(*) as count FROM aircraft_cg WHERE tailnumber = ? AND color = ? AND envelope_name != ?", [($_REQUEST['tailnumber'] ?? null), $envelope_color, $old_envelope_name]);
                $color_check_result = $db->fetchAssoc($color_check_query);
                
                if ($color_check_result['count'] > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'This color is already in use by another envelope']);
                    break;
                }
                
                // Update the envelope
                $update_query = $db->query("UPDATE aircraft_cg SET envelope_name = ?, color = ? WHERE tailnumber = ? AND envelope_name = ?", 
                    [$envelope_name, $envelope_color, ($_REQUEST['tailnumber'] ?? null), $old_envelope_name]);
                
                // Enter in the audit log for envelope update
                $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                $aircraft = $db->fetchAssoc($aircraft_query);
                $audit_data = [
                    'old_envelope_name' => $old_envelope_name,
                    'envelope_name' => $envelope_name,
                    'color' => $envelope_color
                ];
                $audit_message = createAuditMessage("Updated CG envelope", $audit_data);
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                
                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'envelope_name' => $envelope_name,
                    'envelope_color' => $envelope_color
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Envelope name is required']);
            }
            break;
            
        case "loading_del":
            // Delete loading zone
            if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
                $id = ($_REQUEST['id'] ?? null);
                
                // Get loading zone info before deletion for audit log
                $loading_query = $db->query("SELECT * FROM aircraft_weights WHERE id = ?", [$id]);
                $loading_info = $db->fetchAssoc($loading_query);
                
                if ($loading_info) {
                    // Delete the loading zone
                    $db->query("DELETE FROM aircraft_weights WHERE id = ?", [$id]);
                    
                    // Enter in the audit log
                    $aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [($_REQUEST['tailnumber'] ?? null)]);
                    $aircraft = $db->fetchAssoc($aircraft_query);
                    $audit_data = [
                        'id' => $id,
                        'item' => $loading_info['item'],
                        'weight' => $loading_info['weight'],
                        'arm' => $loading_info['arm'],
                        'type' => $loading_info['type']
                    ];
                    $audit_message = createAuditMessage("Deleted aircraft loading item", $audit_data);
                    $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($aircraft['tailnumber'] ?? '') . ': ' . $audit_message]);
                    
                    // Return JSON response
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Loading zone deleted successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Loading zone not found']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Missing loading zone ID']);
            }
            break;
            
        case "basics":
            $active = isset($_REQUEST['active']) && ($_REQUEST['active'] ?? null) != '' ? ($_REQUEST['active'] ?? null) : '0';
            $mlw_value = isset($_REQUEST['max_landing_weight']) && ($_REQUEST['max_landing_weight'] ?? null) !== '' ? ($_REQUEST['max_landing_weight'] ?? null) : null;
            $sql_query = "UPDATE aircraft SET active = ?, tailnumber = ?, makemodel = ?, emptywt = ?, emptycg = ?, maxwt = ?, fuelunit = ?, weight_units = ?, arm_units = ?, fuel_type = ?, max_landing_weight = ? WHERE id = ?";
            $db->query($sql_query, [$active, ($_REQUEST['tailnumber'] ?? null), ($_REQUEST['makemodel'] ?? null), ($_REQUEST['emptywt'] ?? null), ($_REQUEST['emptycg'] ?? null), ($_REQUEST['maxwt'] ?? null), ($_REQUEST['fuelunit'] ?? null), ($_REQUEST['weight_units'] ?? null), ($_REQUEST['arm_units'] ?? null), ($_REQUEST['fuel_type'] ?? null), $mlw_value, ($_REQUEST['id'] ?? null)]);
            $audit_data = [
                'active' => $active,
                'tailnumber' => ($_REQUEST['tailnumber'] ?? null),
                'makemodel' => ($_REQUEST['makemodel'] ?? null),
                'emptywt' => ($_REQUEST['emptywt'] ?? null),
                'emptycg' => ($_REQUEST['emptycg'] ?? null),
                'maxwt' => ($_REQUEST['maxwt'] ?? null),
                'fuelunit' => ($_REQUEST['fuelunit'] ?? null),
                'weight_units' => ($_REQUEST['weight_units'] ?? null),
                'fuel_type' => ($_REQUEST['fuel_type'] ?? null),
                'max_landing_weight' => $mlw_value
            ];
            $audit_message = createAuditMessage("Updated aircraft basics", $audit_data);
            $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, ($_REQUEST['tailnumber'] ?? null) . ': ' . $audit_message]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Aircraft updated successfully']);
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No action specified']);
}
?>