<?php
error_reporting(0); // 실제 운영 시 에러 숨김
ini_set('display_errors', 0);

// CORS 헤더
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} else {
    header('Access-Control-Allow-Origin: *');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
header('Content-Type: application/json; charset=utf-8');

try {
    $host = 'my8003.gabiadb.com';
    $user = 'ksse2907';
    $pass = 'ksse2907!!';
    $db_name = 'ksse';

    $conn = new mysqli($host, $user, $pass, $db_name);
    if ($conn->connect_error) throw new Exception("DB 접속 실패");
    $conn->set_charset("utf8mb4");

    $action = $_REQUEST['action'] ?? '';
    
    // 1. 사원 목록
    if ($action === 'getEmployees') {
        $res = $conn->query("SELECT name FROM ksse_employees ORDER BY name ASC");
        $list = [];
        if ($res) while($r = $res->fetch_assoc()) $list[] = $r['name'];
        else $list = ['admin', 'ksse'];
        echo json_encode($list);
        exit;
    }

    $userID = $_REQUEST['userID'] ?? '';
    $title = $_REQUEST['제목'] ?? '';

    // 2. 삭제
    if ($action === 'delete') {
        $category = $_REQUEST['카테고리'] ?? '';
        if ($category === 'done') {
            $stmt = $conn->prepare("UPDATE ksse_worklogs SET category='delete' WHERE user_id=? AND title=?");
        } else {
            $stmt = $conn->prepare("DELETE FROM ksse_worklogs WHERE user_id=? AND title=?");
        }
        $stmt->bind_param("ss", $userID, $title);
        $stmt->execute();
        echo json_encode(["result" => "success"]);
        exit;
    }

    // 3. 저장/수정
    if ($userID && $title) {
        $content = $_REQUEST['내용'] ?? '';
        $category = $_REQUEST['카테고리'] ?? 'todo';
        $attachment = $_REQUEST['첨부파일'] ?? '';
        $feedback = $_REQUEST['피드백'] ?? '';

        // [수정] get_result 대신 store_result 사용
        $chk = $conn->prepare("SELECT id FROM ksse_worklogs WHERE user_id=? AND title=?");
        $chk->bind_param("ss", $userID, $title);
        $chk->execute();
        $chk->store_result(); // 중요: 결과를 저장해야 num_rows 사용 가능
        $exists = $chk->num_rows > 0;
        $chk->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE ksse_worklogs SET content=?, category=?, attachment=?, feedback=? WHERE user_id=? AND title=?");
            $stmt->bind_param("ssssss", $content, $category, $attachment, $feedback, $userID, $title);
        } else {
            $stmt = $conn->prepare("INSERT INTO ksse_worklogs (user_id, title, content, category, attachment, feedback) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $userID, $title, $content, $category, $attachment, $feedback);
        }

        if (!$stmt->execute()) throw new Exception("DB 오류: " . $stmt->error);
        echo json_encode(["result" => "success"]);
        exit;
    }

    // 4. 조회
    $sql = "SELECT user_id, title, content, category, attachment, feedback FROM ksse_worklogs WHERE category != 'delete' ORDER BY created_at DESC";
    $res = $conn->query($sql);
    $rows = [];
    if ($res) {
        while($r = $res->fetch_assoc()) {
            $rows[] = [$r['user_id'], $r['title'], $r['content'], $r['category'], $r['attachment'], $r['feedback']];
        }
    }
    echo json_encode(["values" => $rows]);

} catch (Exception $e) {
    http_response_code(400); 
    echo json_encode(["result" => "error", "message" => $e->getMessage()]);
}
?>
