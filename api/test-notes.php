<?php
// สร้างไฟล์ใหม่: api/test-notes.php
// เอาไว้เทสว่าปัญหาอยู่ตรงไหน

header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'config/holiday-taxis.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // ทดสอบ 1: เรียก Notes API โดยตรง
    $bookingRef = 'HBEDS-26821653'; // ใช้ booking ที่คุณเทสไว้

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $notesUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/notes/{$bookingRef}";

    echo "=== TEST 1: API CALL ===\n";
    echo "URL: $notesUrl\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $notesUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response: " . substr($response, 0, 500) . "\n";

    if ($httpCode === 200) {
        $notesData = json_decode($response, true);

        if (isset($notesData['notes']['note_0'])) {
            $note = $notesData['notes']['note_0'];
            $formattedNote = $note['note'] . "\nDate: " . $note['notedate'] . "\nUser: " . $note['user'];

            echo "Formatted Note: $formattedNote\n";

            // ทดสอบ 2: บันทึกลง Database โดยตรง
            echo "\n=== TEST 2: DATABASE INSERT ===\n";

            $sql = "UPDATE bookings SET notes = :notes WHERE booking_ref = :ref";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':notes' => $formattedNote,
                ':ref' => $bookingRef
            ]);

            echo "Database Update Result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

            // ทดสอบ 3: เช็คว่าบันทึกได้หรือไม่
            $checkSql = "SELECT notes FROM bookings WHERE booking_ref = :ref";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':ref' => $bookingRef]);
            $savedNotes = $checkStmt->fetchColumn();

            echo "Saved Notes in DB: " . ($savedNotes ? substr($savedNotes, 0, 100) . '...' : 'NULL/EMPTY') . "\n";
        } else {
            echo "ERROR: No note_0 found in API response\n";
        }
    } else {
        echo "ERROR: API call failed with HTTP code $httpCode\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
