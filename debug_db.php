<?php
// debug_utils.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Project.php';

// Setup DB connection
$dbHost = 'localhost';
$dbName = 'ecrivain';
$dbUser = 'root';
$dbPass = '';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

echo "Connected to DB\n";

// Test Project::find
$projectModel = new Project($mysqli);

// Check if any projects exist
$result = $mysqli->query("SELECT * FROM projects LIMIT 1");
$row = $result->fetch_assoc();

if (!$row) {
    echo "No projects found in DB. Cannot test Project::find\n";
    exit;
}

$id = $row['id'];
echo "Found project ID: $id\n";
print_r($row);

$project = $projectModel->find($id);
echo "ProjectModel::find($id) result:\n";
print_r($project);

if (is_array($project) && !array_key_exists('id', $project)) {
    echo "ERROR: 'id' key missing from project array!\n";
} else {
    echo "Project 'id' key exists.\n";
}
