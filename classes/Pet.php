<?php
// classes/Pet.php
// Unrelated example showing a clean PDO CRUD class.
class Pet {
    private $db;
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create($name, $species, $breed = null) {
        $sql = "INSERT INTO pets (name, species, breed)
                VALUES (:name, :species, :breed)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $name, ':species' => $species, ':breed' => $breed
        ]);
        return $this->db->lastInsertId();
    }

    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM pets WHERE pet_id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getAll() {
        return $this->db->query("SELECT * FROM pets")->fetchAll();
    }

    public function update($id, $name) {
        $stmt = $this->db->prepare(
            "UPDATE pets SET name = :name WHERE pet_id = :id");
        return $stmt->execute([':name' => $name, ':id' => $id]);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM pets WHERE pet_id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
