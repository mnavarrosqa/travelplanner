<?php
/**
 * Permission Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Get user's role for a trip
 */
function getUserTripRole($tripId, $userId) {
    $conn = getDBConnection();
    
    // Check if user is owner (original creator)
    $stmt = $conn->prepare("SELECT user_id FROM trips WHERE id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    if ($stmt->fetch()) {
        return 'owner';
    }
    
    // Check trip_users table
    $stmt = $conn->prepare("SELECT role FROM trip_users WHERE trip_id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    $result = $stmt->fetch();
    
    return $result ? $result['role'] : null;
}

/**
 * Check if user has access to trip
 */
function hasTripAccess($tripId, $userId) {
    return getUserTripRole($tripId, $userId) !== null;
}

/**
 * Check if user can edit trip
 */
function canEditTrip($tripId, $userId) {
    $role = getUserTripRole($tripId, $userId);
    return in_array($role, ['owner', 'editor']);
}

/**
 * Check if user can view trip
 */
function canViewTrip($tripId, $userId) {
    return hasTripAccess($tripId, $userId);
}

/**
 * Check if user is trip owner
 */
function isTripOwner($tripId, $userId) {
    return getUserTripRole($tripId, $userId) === 'owner';
}

/**
 * Get all collaborators for a trip
 */
function getTripCollaborators($tripId) {
    $conn = getDBConnection();
    
    // Get owner
    $stmt = $conn->prepare("
        SELECT u.id, u.email, u.first_name, u.last_name, 'owner' as role, t.created_at as joined_at
        FROM trips t
        INNER JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$tripId]);
    $owner = $stmt->fetch();
    
    // Get other collaborators
    $stmt = $conn->prepare("
        SELECT u.id, u.email, u.first_name, u.last_name, tu.role, tu.joined_at
        FROM trip_users tu
        INNER JOIN users u ON tu.user_id = u.id
        WHERE tu.trip_id = ?
        ORDER BY tu.joined_at ASC
    ");
    $stmt->execute([$tripId]);
    $collaborators = $stmt->fetchAll();
    
    $result = [];
    if ($owner) {
        $result[] = $owner;
    }
    $result = array_merge($result, $collaborators);
    
    return $result;
}

/**
 * Add user to trip
 */
function addUserToTrip($tripId, $userId, $role = 'viewer', $invitedBy = null) {
    $conn = getDBConnection();
    
    // Check if already exists
    $stmt = $conn->prepare("SELECT id FROM trip_users WHERE trip_id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    if ($stmt->fetch()) {
        // Update role
        $stmt = $conn->prepare("UPDATE trip_users SET role = ?, invited_by = ? WHERE trip_id = ? AND user_id = ?");
        $stmt->execute([$role, $invitedBy, $tripId, $userId]);
        return true;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO trip_users (trip_id, user_id, role, invited_by) 
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$tripId, $userId, $role, $invitedBy]);
}

/**
 * Remove user from trip
 */
function removeUserFromTrip($tripId, $userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM trip_users WHERE trip_id = ? AND user_id = ?");
    return $stmt->execute([$tripId, $userId]);
}

/**
 * Get user name for display
 */
function getUserDisplayName($user) {
    if ($user['first_name'] || $user['last_name']) {
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }
    return $user['email'];
}
?>


