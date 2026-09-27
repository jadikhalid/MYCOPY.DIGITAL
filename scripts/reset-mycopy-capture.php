<?php
require __DIR__ . '/../db.php';
$row = db()->prepare("SELECT id FROM studio_subjects WHERE studio_code = 'MYCOPY' LIMIT 1");
$row->execute();
$subject = $row->fetch();
if ($subject) {
    $id = (int) $subject['id'];
    $dir = __DIR__ . '/../data/studio/subject_' . $id;
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
    db()->prepare('DELETE FROM studio_subjects WHERE id = :id')->execute(['id' => $id]);
}
echo "MYCOPY capture reset.\n";
