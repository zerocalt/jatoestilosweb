<?php
// admin/app/pages/agenda/actions.php
require_once("../../config/database.php");
require_once("../../config/functions.php");

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    $estabelecimento_id = $_SESSION['estabelecimento_id'];

    if (empty($id) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE agendamentos SET status = :status WHERE id = :id AND estabelecimento_id = :estab_id");
        $stmt->execute(['status' => $status, 'id' => $id, 'estab_id' => $estabelecimento_id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
}
?>