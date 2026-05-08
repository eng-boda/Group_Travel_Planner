<?php

require_once __DIR__ . '/DBController.php';
require_once __DIR__ . '/AuthController.php';

class DocumentController {

    private $db;
    private $uploadDir;

    public function __construct() {
        // Store uploads in a dedicated folder per trip
        $this->uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    // ---------------------------------------------------------------
    // READ
    // ---------------------------------------------------------------

    public function getDocumentsByTrip(int $trip_id): array {
        $this->db = new DBController();
        if (!$this->db->openConnection()) return [];

        $trip_id = (int) $trip_id;
        $query = "
            SELECT d.*, u.name AS uploaded_by_name,
                   a.title AS activity_title
            FROM documents d
            LEFT JOIN users u ON u.user_id = d.user_id
            LEFT JOIN activity a ON a.activity_id = d.activity_id
            WHERE d.trip_id = $trip_id
            ORDER BY d.uploaded_at DESC
        ";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ?: [];
    }

    public function getDocumentById(int $doc_id): ?array {
        $this->db = new DBController();
        if (!$this->db->openConnection()) return null;

        $doc_id = (int) $doc_id;
        $query = "SELECT d.*, u.name AS uploaded_by_name
                  FROM documents d
                  LEFT JOIN users u ON u.user_id = d.user_id
                  WHERE d.doc_id = $doc_id LIMIT 1";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? $result[0] : null;
    }

    // ---------------------------------------------------------------
    // UPLOAD
    // ---------------------------------------------------------------

    /**
     * Handle a multipart file upload.
     * Expects $_FILES['files'] (multiple) and POST fields:
     *   trip_id, activity_id (optional), category (optional)
     */
    public function upload(array $post, array $files, int $user_id): array {
        $trip_id     = (int)($post['trip_id'] ?? 0);
        $activity_id = !empty($post['activity_id']) ? (int)$post['activity_id'] : 'NULL';
        $category    = $this->sanitize($post['category'] ?? 'general');

        if (!$trip_id) {
            return ['success' => false, 'message' => 'No trip selected.'];
        }

        // Support multiple files upload
        $uploaded = [];
        $errors   = [];

        $fileList = $this->normalizeFileArray($files['files'] ?? []);

        foreach ($fileList as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = $file['name'] . ': upload error code ' . $file['error'];
                continue;
            }

            // Validate size (max 20 MB)
            if ($file['size'] > 20 * 1024 * 1024) {
                $errors[] = $file['name'] . ': exceeds 20 MB limit.';
                continue;
            }

            // Validate extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx',
                        'jpg','jpeg','png','gif','webp','txt','csv','zip'];
            if (!in_array($ext, $allowed)) {
                $errors[] = $file['name'] . ': file type not allowed.';
                continue;
            }

            // Build unique filename and move
            $safeName  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file['name']);
            $storedName = time() . '_' . $user_id . '_' . $safeName;
            $destPath  = $this->uploadDir . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $errors[] = $file['name'] . ': failed to save.';
                continue;
            }

            // Persist to DB
            $this->db = new DBController();
            if (!$this->db->openConnection()) {
                $errors[] = $file['name'] . ': DB error.';
                continue;
            }

            $fileName   = $this->db->connection->real_escape_string($file['name']);
            $storedEsc  = $this->db->connection->real_escape_string($storedName);
            $mimeType   = $this->db->connection->real_escape_string($file['type'] ?? '');
            $fileSize   = (int)$file['size'];
            $catEsc     = $this->db->connection->real_escape_string($category);
            $activityVal = ($activity_id === 'NULL') ? 'NULL' : $activity_id;

            $query = "INSERT INTO documents
                        (trip_id, user_id, activity_id, file_name, stored_name, file_type, file_size, category, doc_state, uploaded_at)
                      VALUES
                        ($trip_id, $user_id, $activityVal, '$fileName', '$storedEsc', '$mimeType', $fileSize, '$catEsc', 'active', NOW())";
            $doc_id = $this->db->insert($query);
            $this->db->closeConnection();

            if ($doc_id) {
                $uploaded[] = ['doc_id' => $doc_id, 'file_name' => $file['name']];
            } else {
                $errors[] = $file['name'] . ': failed to save record.';
            }
        }

        if (empty($uploaded) && !empty($errors)) {
            return ['success' => false, 'message' => implode(' | ', $errors)];
        }

        return [
            'success'  => true,
            'uploaded' => $uploaded,
            'errors'   => $errors,
            'message'  => count($uploaded) . ' file(s) uploaded successfully.'
        ];
    }

    // ---------------------------------------------------------------
    // ATTACH to activity
    // ---------------------------------------------------------------

    public function attachToActivity(int $doc_id, ?int $activity_id, int $user_id): array {
        $this->db = new DBController();
        if (!$this->db->openConnection()) return ['success' => false];

        $actVal = $activity_id ? (int)$activity_id : 'NULL';
        $query  = "UPDATE documents SET activity_id = $actVal WHERE doc_id = $doc_id";

        $ok = $this->db->connection->query($query);
        $this->db->closeConnection();
        return ['success' => (bool)$ok];
    }

    // ---------------------------------------------------------------
    // DELETE
    // ---------------------------------------------------------------

    public function delete(int $doc_id, int $user_id): array {
        $doc = $this->getDocumentById($doc_id);
        if (!$doc) return ['success' => false, 'message' => 'Document not found.'];

        // Only uploader or trip leader can delete (simple check: owner)
        if ((int)$doc['user_id'] !== $user_id) {
            // Allow anyway for now (leader check can be added with RoleController)
        }

        // Remove physical file
        $filePath = $this->uploadDir . $doc['stored_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Remove DB record
        $this->db = new DBController();
        if (!$this->db->openConnection()) return ['success' => false];

        $doc_id = (int)$doc_id;
        $ok = $this->db->connection->query("DELETE FROM documents WHERE doc_id = $doc_id");
        $this->db->closeConnection();

        return ['success' => (bool)$ok, 'message' => 'Document deleted.'];
    }

    // ---------------------------------------------------------------
    // EXPORT: download a single file
    // ---------------------------------------------------------------

    public function download(int $doc_id): void {
        $doc = $this->getDocumentById($doc_id);
        if (!$doc) { http_response_code(404); exit('Not found'); }

        $filePath = $this->uploadDir . $doc['stored_name'];
        if (!file_exists($filePath)) { http_response_code(404); exit('File missing'); }

        $fileName = $doc['file_name'];
        $mimeType = $doc['file_type'] ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }

    // ---------------------------------------------------------------
    // EXPORT: ZIP all documents for a trip
    // ---------------------------------------------------------------

    public function exportZip(int $trip_id): void {
        $docs = $this->getDocumentsByTrip($trip_id);
        if (empty($docs)) { exit('No documents to export.'); }

        $zipName = tempnam(sys_get_temp_dir(), 'tripdocs_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipName, ZipArchive::CREATE) !== true) {
            exit('Could not create ZIP.');
        }

        foreach ($docs as $doc) {
            $filePath = $this->uploadDir . $doc['stored_name'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $doc['file_name']);
            }
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="trip_' . $trip_id . '_documents.zip"');
        header('Content-Length: ' . filesize($zipName));
        readfile($zipName);
        unlink($zipName);
        exit;
    }

    // ---------------------------------------------------------------
    // SEARCH
    // ---------------------------------------------------------------

    public function search(int $trip_id, string $query): array {
        $this->db = new DBController();
        if (!$this->db->openConnection()) return [];

        $trip_id = (int)$trip_id;
        $q = $this->db->connection->real_escape_string('%' . $query . '%');
        $sql = "
            SELECT d.*, u.name AS uploaded_by_name, a.title AS activity_title
            FROM documents d
            LEFT JOIN users u ON u.user_id = d.user_id
            LEFT JOIN activity a ON a.activity_id = d.activity_id
            WHERE d.trip_id = $trip_id
              AND (d.file_name LIKE '$q' OR d.category LIKE '$q' OR a.title LIKE '$q')
            ORDER BY d.uploaded_at DESC
        ";
        $result = $this->db->select($sql);
        $this->db->closeConnection();
        return $result ?: [];
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------

    private function sanitize(string $val): string {
        return htmlspecialchars(strip_tags(trim($val)));
    }

    /** Normalize $_FILES['files'] whether single or multiple uploads */
    private function normalizeFileArray(array $files): array {
        if (empty($files) || !isset($files['name'])) return [];
        if (is_array($files['name'])) {
            $out = [];
            foreach ($files['name'] as $i => $name) {
                $out[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
            return $out;
        }
        return [$files]; // single file
    }

    /** Human-readable file size */
    public static function formatSize(int $bytes): string {
        if ($bytes < 1024)        return $bytes . ' B';
        if ($bytes < 1048576)     return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824)  return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    /** Return icon character by extension */
    public static function fileIcon(string $fileName): string {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return match($ext) {
            'pdf'              => '📄',
            'doc', 'docx'     => '📝',
            'xls', 'xlsx'     => '📊',
            'ppt', 'pptx'     => '📑',
            'jpg','jpeg','png','gif','webp' => '🖼️',
            'zip', 'rar'      => '🗜️',
            'txt', 'csv'      => '📃',
            default            => '📎',
        };
    }
}
?>