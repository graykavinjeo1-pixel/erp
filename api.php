<?php
// 1. 에러 발생 시 HTML이 아닌 JSON으로 응답하도록 설정
error_reporting(E_ALL); // 디버깅용 (안정화되면 0으로 변경)
ini_set('display_errors', 0); 

// 2. CORS 헤더 설정
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
    // 3. DB 접속
    $host = 'my8003.gabiadb.com';
    $user = 'ksse2907';
    $pass = 'ksse2907!!';
    $db_name = 'ksse';

    $conn = new mysqli($host, $user, $pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("DB 연결 실패: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // 4. 파라미터 수신
    $action     = $_REQUEST['action'] ?? '';
    $userID     = $_REQUEST['userID'] ?? '';
    $title      = $_REQUEST['제목'] ?? '';
    $content    = $_REQUEST['내용'] ?? '';
    $category   = $_REQUEST['카테고리'] ?? '';
    $attachment = $_REQUEST['첨부파일'] ?? '';
    $feedback   = $_REQUEST['피드백'] ?? '';

    // [기능 1] 사원 목록 조회
    if ($action === 'getEmployees') {
        $res = $conn->query("SELECT name FROM ksse_employees ORDER BY name ASC");
        $list = [];
        if ($res) while($r = $res->fetch_assoc()) $list[] = $r['name'];
        else $list = ['admin', 'ksse'];
        echo json_encode($list);
        exit;
    }
    
    // [기능 1-1] 사원 색상 목록 조회 (추가된 기능)
if ($action === 'getColors') {
    // ksse_employees 테이블에서 이름과 색상 코드를 가져옴
    $sql = "SELECT name, color_code FROM ksse_employees";
    $result = $conn->query($sql);
    $colors = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            // "이름": "색상코드" 형태의 JSON 객체로 만듦
            $colors[$row['name']] = $row['color_code']; 
        }
    }
    echo json_encode($colors);
    exit;
}
    // [기능 2] 삭제
    if ($action === 'delete') {
        if ($category === 'done') {
            $stmt = $conn->prepare("UPDATE ksse_worklogs SET category = 'delete' WHERE user_id = ? AND title = ?");
        } else {
            $stmt = $conn->prepare("DELETE FROM ksse_worklogs WHERE user_id = ? AND title = ?");
        }
        $stmt->bind_param("ss", $userID, $title);
        $stmt->execute();
        echo json_encode(["result" => "success"]);
        exit;
    }

    // [기능 3] 데이터 저장/수정 (Insert or Update)
    if ($userID && $title) {
        // [중요 수정] get_result() 대신 store_result() 사용 (가비아 호환성)
        $check = $conn->prepare("SELECT id FROM ksse_worklogs WHERE user_id = ? AND title = ?");
        if (!$check) throw new Exception("SQL 준비 실패(SELECT): " . $conn->error);
        
        $check->bind_param("ss", $userID, $title);
        $check->execute();
        $check->store_result(); // 결과를 메모리에 저장
        $exists = $check->num_rows > 0; // 저장된 결과의 개수 확인
        $check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE ksse_worklogs SET content=?, category=?, attachment=?, feedback=? WHERE user_id=? AND title=?");
            if (!$stmt) throw new Exception("SQL 준비 실패(UPDATE): 테이블 컬럼(attachment 등)을 확인하세요. " . $conn->error);
            $stmt->bind_param("ssssss", $content, $category, $attachment, $feedback, $userID, $title);
        } else {
            $stmt = $conn->prepare("INSERT INTO ksse_worklogs (user_id, title, content, category, attachment, feedback) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("SQL 준비 실패(INSERT): 테이블 컬럼(attachment 등)을 확인하세요. " . $conn->error);
            $stmt->bind_param("ssssss", $userID, $title, $content, $category, $attachment, $feedback);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("쿼리 실행 에러: " . $stmt->error);
        }
        echo json_encode(["result" => "success"]);
        exit;
    }

    // [기능 4] 목록 조회
    $sql = "SELECT user_id, title, content, category, attachment, feedback FROM ksse_worklogs WHERE category != 'delete' ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $rows = [];
    if ($result) {
        while($r = $result->fetch_assoc()) {
            $rows[] = [
                $r['user_id'], $r['title'], $r['content'], $r['category'], 
                $r['attachment'] ?? '', $r['feedback'] ?? ''
            ];
        }
    }
    echo json_encode(["values" => $rows]);

} catch (Exception $e) {
    // 500 에러 대신 400 에러를 내고 JSON으로 이유를 설명
    http_response_code(400); 
    echo json_encode(["result" => "error", "message" => $e->getMessage()]);
}

if (isset($conn)) $conn->close();
?>