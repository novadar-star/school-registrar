<?php
/**
 * doc_download.php — Serve a document with a tagged filename
 * Usage: doc_download.php?sr_id=123
 */
session_start();
include('../mysql/db.php');
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

$sr_id = intval($_GET['sr_id'] ?? 0);
if (!$sr_id) { http_response_code(404); exit('Not found'); }

$row = $conn->query("
  SELECT sr.file_path, r.name as doc_name,
         CONCAT(s.last_name, ', ', s.first_name) as student_name
  FROM student_requirements sr
  JOIN requirements r ON r.id = sr.requirement_id
  JOIN students s ON s.id = sr.student_id
  WHERE sr.id = $sr_id
")->fetch_assoc();

if (!$row || empty($row['file_path'])) { http_response_code(404); exit('File not found'); }

$file_path = __DIR__ . '/uploads/' . $row['file_path'];
if (!file_exists($file_path)) { http_response_code(404); exit('File missing'); }

$ext  = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
$safe = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $row['student_name'] . ' - ' . $row['doc_name']);
$download_name = trim($safe) . '.' . $ext;

$mime_map = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit();
