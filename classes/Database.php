<?php
// classes/Database.php
// PDO wrapper implementing the Singleton pattern.
class Database extends PDO {
    private static $instance = null;

    private function __construct($dsn, $user, $pass) {
        parent::__construct($dsn, $user, $pass);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function getInstance($dsn, $user = null, $pass = null) {
        if (self::$instance === null) {
            self::$instance = new Database($dsn, $user, $pass);
        }
        return self::$instance;
    }

    public function insert($table, array $data) {
        $cols = implode(", ", array_keys($data));
        $phs = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO $table ($cols) VALUES ($phs)";
        $stmt = $this->prepare($sql);
        $stmt->execute($data);
        return $this->lastInsertId();
    }

    /**
     * Added to match the class diagram (update(table, data, where)).
     * $data and $where are [column => value]. Table/column names come from our own
     * code (never from user input); all VALUES are bound parameters.
     * Returns the number of rows changed.
     */
    public function update($table, array $data, array $where) {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "$col = :set_$col";
            $params["set_$col"] = $val;
        }
        $cond = [];
        foreach ($where as $col => $val) {
            $cond[] = "$col = :where_$col";
            $params["where_$col"] = $val;
        }
        $sql = "UPDATE $table SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $cond);
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Added to match the class diagram (delete(table, where)). Returns rows deleted. */
    public function delete($table, array $where) {
        $cond = [];
        foreach ($where as $col => $val) {
            $cond[] = "$col = :$col";
        }
        $sql = "DELETE FROM $table WHERE " . implode(" AND ", $cond);
        $stmt = $this->prepare($sql);
        $stmt->execute($where);
        return $stmt->rowCount();
    }

    public function getRows($sql, array $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRow($sql, array $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
}
