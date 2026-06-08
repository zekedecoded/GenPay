<?php

namespace Classes;



require_once __DIR__ . '/connection/config.php';
require_once __DIR__ . '/connection/pdo.php';

class Record
{
    public int $id;
    public string $stud_name;
    public string $brgy;
    public string $city;
    public string $prov;

    private $con;
    private string $response;

    public function __construct($db){
        $this->con = $db;
    }

    
    public function loginUser() {
        if (isset($_POST['login'])) {
            

            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $stmt = $this->con->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount()) {
                $user = $stmt->fetch();

                $storedPassword = (string) ($user['password'] ?? '');
                $validPassword = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

                if ($validPassword) {
                    $userId = (int) ($user['id'] ?? $user['userID'] ?? 0);
                    $roleId = (int) ($user['roleID'] ?? 0);

                    $_SESSION['userID'] = $userId;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['roleID'] = $roleId;
                    $_SESSION['role'] = [1 => 'student', 2 => 'merchant', 3 => 'admin'][$roleId] ?? 'user';

                    header("Location: index.php");
                    exit;
                } else {
                    return "Invalid password";
                }
            } else {
                return "User not found";
            }
        }
    }
    











    
    public function getAllInfo(){
        $stmt = $this->con->prepare('SELECT * FROM personal');
        $stmt->execute();
        if(!$stmt->rowCount()){
            return [];
        }
        return $stmt->fetchAll();
    }

    public function getAllEducation(){
        $stmt = $this->con->prepare('SELECT * FROM education');
        $stmt->execute();
        if(!$stmt->rowCount()){
            return [];
        }
        return $stmt->fetchAll();
    }

    public function getAllHistory(){
        $stmt = $this->con->prepare('SELECT * FROM employment');
        $stmt->execute();
        if(!$stmt->rowCount()){
            return [];
        }
        return $stmt->fetchAll();
    }

    
    public function getPostInfo(){
        $this->firstName = $_POST['firstName'];
        $this->middleName = $_POST['middleName'];
        $this->lastName = $_POST['lastName'];
        $this->suffix = $_POST['suffix'];
        $this->mobile = $_POST['mobile'];
        $this->email = $_POST['email'];
        $this->streetName = $_POST['streetName'];
        $this->barangayName = $_POST['barangayName'];
        $this->cityName = $_POST['cityName'];
        $this->provinceName = $_POST['provinceName'];
        $this->dob = $_POST['dob'];
        $this->gender = $_POST['gender'];
        $this->fatherName = $_POST['fatherName'];
        $this->languages = $_POST['languages'];
        $this->maritalStatus = $_POST['maritalStatus'];
        $this->religion = $_POST['religion'];
        $this->hobbies = $_POST['hobbies'];
    }

    public function getPostEducation(){
        $this->acad_level = $_POST['acad_level'];
        $this->course = $_POST['course'];
        $this->year_graduated = $_POST['year_graduated'];
        $this->skills = $_POST['skills'];
    }

    public function getPostHistory(){
        $this->company = $_POST['company'];
        $this->position = $_POST['position'];
        $this->date_joining = $_POST['date_joining'];
        $this->date_exit = $_POST['date_exit'];
    }

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    

    public function dataEditing($id) {
        $this->getPostInfo();
        if (!empty($_POST)) {
            $stmt = $this->con->prepare("UPDATE personal SET firstName=?, middleName=?, lastName=?, suffix=?, mobile=?, 
        email=?, streetName=?, barangayName=?, cityName=?, provinceName=?, dob=?, gender=?, fatherName=?, languages=?, 
        maritalStatus=?, religion=?, hobbies=? WHERE id=?");
        $stmt->execute([
            $this->firstName,
            $this->middleName,
            $this->lastName,
            $this->suffix,
            $this->mobile,
            $this->email,
            $this->streetName,
            $this->barangayName,
            $this->cityName,
            $this->provinceName,
            $this->dob,
            $this->gender,
            $this->fatherName,
            $this->languages,
            $this->maritalStatus,
            $this->religion,
            $this->hobbies,
            $id,
        ]);

        $this->responseSQL($stmt);
        
        header('Location: ' . BASE_URL . '/index.php');
        }
    }

    public function dataEditingEducation($id) {
        $this->getPostEducation();
        if (!empty($_POST)) {
            $stmt = $this->con->prepare("UPDATE education SET acad_level=?, course=?, year_graduated=?, skills=? WHERE education_id=?");
        $stmt->execute([
            $this->acad_level,
            $this->course,
            $this->year_graduated,
            $this->skills,
            $id,
        ]);

        $this->responseSQL($stmt);
        
        header('Location: ' . BASE_URL . '/index.php');
        }
    }

    public function dataEditingHistory($id) {
        $this->getPostHistory();
        if (!empty($_POST)) {
            $stmt = $this->con->prepare("UPDATE employment SET company=?, position=?, date_joining=?, date_exit=? WHERE employment_id=?");
        $stmt->execute([
            $this->company,
            $this->position,
            $this->date_joining,
            $this->date_exit,
            $id,
        ]);

        $this->responseSQL($stmt);
        
        header('Location: ' . BASE_URL . '/index.php');
        }
    }

    public function dataEditingOrig($id) {
        if (!$id) return;

        $this->getPostInfo();
        $stmt = $this->con->prepare("UPDATE personal SET firstName=?, middleName=?, lastName=?, suffix=?, mobile=?, 
        email=?, streetName=?, barangayName=?, cityName=?, provinceName=?, dob=?, gender=?, fatherName=?, languages=?, 
        maritalStatus=?, religion=?, hobbies=? WHERE id=?");
        $stmt->execute([
            $this->firstName,
            $this->middleName,
            $this->lastName,
            $this->suffix,
            $this->mobile,
            $this->email,
            $this->streetName,
            $this->barangayName,
            $this->cityName,
            $this->provinceName,
            $this->dob,
            $this->gender,
            $this->fatherName,
            $this->languages,
            $this->maritalStatus,
            $this->religion,
            $this->hobbies,
            $id,
        ]);

        $this->responseSQL($stmt);
        header('Location: ' . BASE_URL . '/index.php');
    }

    public function dataEdit($id) {
        if (!$id) return 0;

        $stmt = $this->con->prepare("SELECT * FROM personal WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() ? $stmt->fetch() : 0;
    }

    
    public function dataViewInfo($id) {
        if (!$id) return 0;

        $stmt = $this->con->prepare("SELECT * FROM personal WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() ? $stmt->fetch() : 0;
    }

    public function dataViewEducation($id) {
        if (!$id) return 0;

        $stmt = $this->con->prepare("SELECT * FROM education WHERE education_id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() ? $stmt->fetch() : 0;
    }

    public function dataViewHistory($id) {
        if (!$id) return 0;

        $stmt = $this->con->prepare("SELECT * FROM employment WHERE employment_id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() ? $stmt->fetch() : 0;
    }

    
    public function dataAddInfo(){
        if (isset($_POST['dataAddInfo'])) {
            $this->getPostInfo();
            $stmt = $this->con->prepare("INSERT INTO personal (firstName, middleName, lastName, suffix,
            mobile, email, streetName, barangayName, cityName, provinceName, dob, gender, fatherName, languages, maritalStatus, religion, hobbies) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $this->firstName,
                $this->middleName,
                $this->lastName,
                $this->suffix,
                $this->mobile,
                $this->email,
                $this->streetName,
                $this->barangayName,
                $this->cityName,
                $this->provinceName,
                $this->dob,
                $this->gender,
                $this->fatherName,
                $this->languages,
                $this->maritalStatus,
                $this->religion,
                $this->hobbies,
            ]);
            $this->responseSQL($stmt);
            header('Location: index.php');
        }
    }

    public function dataAddEducation(){
        if (isset($_POST['dataAddEducation'])) {
            $this->getPostEducation();
            $stmt = $this->con->prepare("INSERT INTO education (acad_level, course, year_graduated, skills) 
            VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $this->acad_level,
                $this->course,
                $this->year_graduated,
                $this->skills,
            ]);
            $this->responseSQL($stmt);
            header('Location: index.php');
        }
    }

    public function dataAddHistory(){
        if (isset($_POST['dataAddHistory'])) {
            $this->getPostHistory();
            $stmt = $this->con->prepare("INSERT INTO employment (company, position, date_joining, date_exit) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $this->company,
                $this->position,
                $this->date_joining,
                $this->date_exit,
            ]);
            $this->responseSQL($stmt);
            header('Location: index.php');
        }
    }

    
    
    
    
    
    
    
    
    
    

    public function dataDeleteInfo($id) {
        $stmt = $this->con->prepare("DELETE FROM personal WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function dataDeleteEducation($id) {
        $stmt = $this->con->prepare("DELETE FROM education WHERE education_id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function dataDeleteHistory($id) {
        $stmt = $this->con->prepare("DELETE FROM employment WHERE employment_id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function responseSQL($stmt){
        if($stmt->rowCount()){
            $this->response = 'success';
        }
        $this->response = 'failed';
    }

    public function getResponse(){
        return $this->response;
    }
}










?>
