<?php
/**
 * A library of generic helper functions for CRUD (Create, Read, Update, Delete) operations.
 */

// --- CREATE ---
function insert_record(PDO $pdo, string $table, array $data): array
{
    // ... existing code ...
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        return ['success' => true, 'message' => 'رکورد با موفقیت ایجاد شد.', 'id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        // Handle unique constraint violation specifically if needed
        if ($e->getCode() == '23000') { // Integrity constraint violation
             // You might want to return a more specific message or handle it differently
             return ['success' => false, 'message' => 'خطا: داده تکراری یا نقض محدودیت پایگاه داده. ' . $e->getMessage()];
        }
        return ['success' => false, 'message' => 'خطا در ایجاد رکورد: ' . $e->getMessage()];
    }
}

// --- READ (All) ---
function find_all(PDO $pdo, string $sql, array $params = []): array
{
    // ... existing code ...
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        // Use FETCH_ASSOC to ensure associative array keys
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error instead of just returning empty array for better debugging
        error_log("Error in find_all: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($params, true));
        return [];
    }
}

// --- READ (Single by NUMERIC ID) ---
function find_by_id(PDO $pdo, string $table, int $id, string $primary_key): ?array
{
    // ... existing code ...
    // Ensure primary_key is safely quoted if needed, though usually it's a known column name
    $sql = "SELECT * FROM {$table} WHERE `{$primary_key}` = ? LIMIT 1";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    } catch (PDOException $e) {
         error_log("Error in find_by_id: " . $e->getMessage() . " | Table: " . $table . " | ID: " . $id);
        return null;
    }
}

// --- READ (Single by ANY Field) ---
function find_one_by_field(PDO $pdo, string $table, string $field, $value): ?array
{
    // ... existing code ...
    // Quote the field name safely
    $sql = "SELECT * FROM {$table} WHERE `{$field}` = ? LIMIT 1";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    } catch (PDOException $e) {
         error_log("Error in find_one_by_field: " . $e->getMessage() . " | Table: " . $table . " | Field: " . $field);
        return null;
    }
}

// --- UPDATE ---
// Modified to accept optional where_clause and params, making $id and $primary_key optional when where_clause is provided
function update_record(PDO $pdo, string $table, array $data, ?int $id = null, ?string $primary_key = null, ?string $where_clause = null, array $params = []): array
{
    // ... existing code ...
    if (empty($data)) {
        return ['success' => false, 'message' => 'هیچ داده‌ای برای بروزرسانی ارائه نشده است.'];
    }

    $set_parts = [];
    $update_params = []; // Use a separate array for parameters in the SET clause

    // Prepare SET clause placeholders and parameters
    foreach ($data as $key => $value) {
        $placeholder = ":set_{$key}"; // Use a prefix to avoid collision with WHERE params
        $set_parts[] = "`{$key}` = {$placeholder}";
        $update_params[$placeholder] = $value;
    }
    $set_clause = implode(', ', $set_parts);

    // Determine the WHERE clause
    if ($where_clause !== null) {
        $sql = "UPDATE {$table} SET {$set_clause} WHERE {$where_clause}";
        $execute_params = array_merge($update_params, $params); // Combine SET and WHERE parameters
    } elseif ($id !== null && $primary_key !== null) {
        $sql = "UPDATE {$table} SET {$set_clause} WHERE `{$primary_key}` = :id";
        $update_params[':id'] = $id; // Add id to the SET parameters
        $execute_params = $update_params;
    } else {
        return ['success' => false, 'message' => 'برای بروزرسانی یا باید ID و Primary Key یا یک Where Clause ارائه شود.'];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($execute_params);
        return ['success' => true, 'message' => 'رکورد(ها) با موفقیت بروزرسانی شد.', 'affected_rows' => $stmt->rowCount()];
    } catch (PDOException $e) {
        // Log error for debugging
        error_log("Error in update_record: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($execute_params, true));
        // Handle unique constraint violation specifically if needed
        if ($e->getCode() == '23000') {
            return ['success' => false, 'message' => 'خطا: داده تکراری یا نقض محدودیت پایگاه داده. ' . $e->getMessage()];
        }
        return ['success' => false, 'message' => 'خطا در بروزرسانی رکورد(ها): ' . $e->getMessage()];
    }
}


// --- DELETE ---
function delete_record(PDO $pdo, string $table, int $id, string $primary_key): array
{
    // ... existing code ...
    // Quote primary key safely
    $sql = "DELETE FROM {$table} WHERE `{$primary_key}` = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'رکورد با موفقیت حذف شد.'];
        } else {
            return ['success' => false, 'message' => 'هیچ رکوردی برای حذف پیدا نشد.'];
        }
    } catch (PDOException $e) {
         error_log("Error in delete_record: " . $e->getMessage() . " | Table: " . $table . " | ID: " . $id);
         // Check for foreign key constraint violation
         if ($e->getCode() == '23000') {
             return ['success' => false, 'message' => 'خطا: امکان حذف این رکورد وجود ندارد زیرا رکوردهای دیگری به آن وابسته هستند.'];
         }
        return ['success' => false, 'message' => 'خطا در حذف رکورد: ' . $e->getMessage()];
    }
}


?>
