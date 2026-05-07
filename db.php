<?php
require_once 'helpers.php';

class Database {
    public $db;

    public function __construct() {
        $this->db = new SQLite3(DB_PATH);
        $this->db->exec('PRAGMA foreign_keys = ON'); // Enable foreign key constraints
        $this->createTables();
    }

    private function createTables() {
        // Users table with language, theme, and allowed_path
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                language TEXT DEFAULT "en",
                theme TEXT DEFAULT "dark",
                allowed_path TEXT NOT NULL DEFAULT "",
                created_at INTEGER NOT NULL
            )
        ');

        // Shares table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS shares (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hash TEXT UNIQUE NOT NULL,
                file_path TEXT NOT NULL,
                password TEXT,
                expires_at INTEGER,
                delete_after_download INTEGER DEFAULT 0,
                file_delete_at INTEGER,
                download_count INTEGER DEFAULT 0,
                created_at INTEGER NOT NULL,
                created_by INTEGER,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )
        ');
    }

    // === USER METHODS ===

    public function getUser($username) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function verifyUser($username, $password) {
        $user = $this->getUser($username);
        if (!$user) {
            return false;
        }
        return password_verify($password, $user['password']);
    }

    public function changePassword($username, $newPassword) {
        $stmt = $this->db->prepare('
            UPDATE users SET password = :password WHERE username = :username
        ');
        $stmt->bindValue(':password', password_hash($newPassword, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function updatePreferences($username, $language, $theme) {
        $stmt = $this->db->prepare('
            UPDATE users SET language = :language, theme = :theme WHERE username = :username
        ');
        $stmt->bindValue(':language', $language, SQLITE3_TEXT);
        $stmt->bindValue(':theme', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getAllUsers() {
        $result = $this->db->query('SELECT id, username, language, theme, allowed_path, created_at FROM users ORDER BY username');
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        return $users;
    }

    public function createUser($username, $password, $language = 'en', $theme = 'dark', $allowedPath = '') {
        $stmt = $this->db->prepare('
            INSERT INTO users (username, password, language, theme, allowed_path, created_at)
            VALUES (:username, :password, :language, :theme, :allowed_path, :created_at)
        ');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':language', $language, SQLITE3_TEXT);
        $stmt->bindValue(':theme', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':allowed_path', $allowedPath, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
        return $stmt->execute();
    }

    public function deleteUser($username) {
        // Don't allow deleting the last user
        $result = $this->db->query('SELECT COUNT(*) as count FROM users');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['count'] <= 1) {
            return false;
        }

        // Get user ID first
        $user = $this->getUser($username);
        if (!$user) {
            return false;
        }

        // Set created_by to NULL for orphaned shares
        $stmt = $this->db->prepare('UPDATE shares SET created_by = NULL WHERE created_by = :id');
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();

        // Delete user
        $stmt = $this->db->prepare('DELETE FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        return $stmt->execute();
    }

    // === SHARE METHODS ===

    public function createShare($filePath, $password = null, $expiresAt = null, $deleteAfter = false, $fileDeleteAt = null, $createdBy = null) {
        $hash = generateHash();
        $stmt = $this->db->prepare('
            INSERT INTO shares (hash, file_path, password, expires_at, delete_after_download, file_delete_at, created_at, created_by)
            VALUES (:hash, :file_path, :password, :expires_at, :delete_after, :file_delete_at, :created_at, :created_by)
        ');

        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->bindValue(':file_path', $filePath, SQLITE3_TEXT);
        $stmt->bindValue(':password', $password ? password_hash($password, PASSWORD_DEFAULT) : null, SQLITE3_TEXT);
        $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_INTEGER);
        $stmt->bindValue(':delete_after', $deleteAfter ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':file_delete_at', $fileDeleteAt, SQLITE3_INTEGER);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':created_by', $createdBy, SQLITE3_INTEGER);

        $stmt->execute();
        return $hash;
    }

    public function getShare($hash) {
        $stmt = $this->db->prepare('SELECT * FROM shares WHERE hash = :hash');
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function incrementDownload($hash) {
        $stmt = $this->db->prepare('UPDATE shares SET download_count = download_count + 1 WHERE hash = :hash');
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function deleteShare($hash) {
        $stmt = $this->db->prepare('DELETE FROM shares WHERE hash = :hash');
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function getAllShares() {
        $result = $this->db->query('
            SELECT s.*, u.username as created_by_name 
            FROM shares s
            LEFT JOIN users u ON s.created_by = u.id
            ORDER BY s.created_at DESC
        ');
        $shares = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $shares[] = $row;
        }
        return $shares;
    }

    public function cleanup() {
        // Delete expired shares
        $this->db->exec('DELETE FROM shares WHERE expires_at IS NOT NULL AND expires_at < ' . time());
    }
    
    public function getFilesToDelete() {
        $result = $this->db->query('
            SELECT * FROM shares 
            WHERE file_delete_at IS NOT NULL 
            AND file_delete_at < ' . time()
        );
        
        $shares = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $shares[] = $row;
        }
        return $shares;
    }

    public function hasUsers(){
        return $this->db->querySingle('SELECT COUNT(*) FROM users') > 0;
    }
}
?>
