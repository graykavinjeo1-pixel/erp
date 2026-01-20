<?php
// 1. 에러 출력 방지 및 헤더 설정
error_reporting(0);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

// 2. DB 접속 (성공했던 정보 그대로 적용)
$host = 'my8003.gabiadb.com';
$user = 'ksse2907';
$pass = 'ksse2907!!';
$db   = 'ksse';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["result" => "error", "message" => "DB 연결 실패"]));
}
$conn->set_charset("utf8mb4");

// 3. 파라미터 수신
$action     = $_REQUEST['action'] ?? '';
$userID     = $_REQUEST['userID'] ?? '';
$title      = $_REQUEST['제목'] ?? '';
$content    = $_REQUEST['내용'] ?? '';
$category   = $_REQUEST['카테고리'] ?? '';
$attachment = $_REQUEST['첨부파일'] ?? '';
$feedback   = $_REQUEST['피드백'] ?? '';

// [기능 1] 사원 목록 조회
if ($action === 'getEmployees') {
    $sql = "SELECT name FROM ksse_employees ORDER BY name ASC";
    $result = $conn->query($sql);
    $list = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) $list[] = $row['name'];
    } else {
        $list = ['admin', 'ksse']; // 기본값
    }
    echo json_encode($list);
    exit;
}

// [기능 2] 조건부 삭제 (완료 상태면 delete로 변경, 아니면 행 삭제)
if ($action === 'delete') {
    if ($category === 'done') {
        $stmt = $conn->prepare("UPDATE ksse_worklogs SET category = 'delete' WHERE user_id = ? AND title = ?");
        $stmt->bind_param("ss", $userID, $title);
        $stmt->execute();
        echo json_encode(["result" => "success", "message" => "기록이 보관되었습니다."]);
    } else {
        $stmt = $conn->prepare("DELETE FROM ksse_worklogs WHERE user_id = ? AND title = ?");
        $stmt->bind_param("ss", $userID, $title);
        $stmt->execute();
        echo json_encode(["result" => "success", "message" => "영구 삭제되었습니다."]);
    }
    exit;
}

// [기능 3] 데이터 저장/수정 (Insert or Update)
if ($userID && $title) {
    $check = $conn->prepare("SELECT id FROM ksse_worklogs WHERE user_id = ? AND title = ?");
    $check->bind_param("ss", $userID, $title);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE ksse_worklogs SET content=?, category=?, attachment=?, feedback=? WHERE user_id=? AND title=?");
        $stmt->bind_param("ssssss", $content, $category, $attachment, $feedback, $userID, $title);
    } else {
        $stmt = $conn->prepare("INSERT INTO ksse_worklogs (user_id, title, content, category, attachment, feedback) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $userID, $title, $content, $category, $attachment, $feedback);
    }
    $stmt->execute();
    echo json_encode(["result" => "success"]);
    exit;
}

// [기능 4] 업무 목록 전체 조회 (category가 delete가 아닌 것만)
$sql = "SELECT user_id, title, content, category, attachment, feedback FROM ksse_worklogs WHERE category != 'delete' ORDER BY created_at DESC";
$result = $conn->query($sql);
$rows = [];
if ($result) {
    while($r = $result->fetch_assoc()) {
        $rows[] = [$r['user_id'], $r['title'], $r['content'], $r['category'], $r['attachment'], $r['feedback']];
    }
}
echo json_encode(["values" => $rows]);

$conn->close();
?>