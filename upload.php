<?php

try {
  //Make sure a file has been submitted
  if (!isset($_FILES['file'])) {
    throw new Exception("Aucun fichier trouvé !");
  }
  $file = $_FILES['file'];

  //File information and details
  $filename = $file['name'];
  $filetmp = $file['tmp_name'];
  $filesize = $file['size'];
  $error = $file['error'];
  $maxsize = 104857600;

  //Find file extension for whitelisting
  $extension = explode('.', $filename);
  $extension = strtolower(end($extension));

  //List of allowed exstensions
  $allowed = array('png', 'jpg', 'jpeg', 'gif', 'webm', 'mp4', 'wmv', 'mp3', 'flac', 'ogg', 'zip', 'css', 'pdf', 'zip', 'rar', 'm3u', 'm3u8', 'txt');

  //Check if file is allowed
  if(!in_array($extension, $allowed)) {
    throw new Exception("Ce type de fichier n'est pas encore autorisé, désolé. Si vous pensez qu'il devrait l'être, merci de mentionner @Sangigi_Esenjin sur Twitter et je regarderais cela !");
  }

  //Check if there is an error
  if($error !== 0) {
    throw new Exception("Notre serveur a détecté un problème avec ce fichier. Essaie encore.");
  }

  //Make sure the filesize is ok
  if($filesize > $maxsize) {
    throw new Exception();
  }

  //Generate a file name, and regenerate it if a file with that name already exists
  do {
    $newname = strtoupper(substr(hash("sha256", $filename . (rand() * 100)), 0, 6)) . "." . $extension;
  } while (file_exists("/file/" . $newname));

  //Set file location
  $location = 'file/' . $newname;

  //Move file to storage folder
  if(!move_uploaded_file($filetmp, $location)) {
    throw new Exception("Impossible de déplacer le fichier dans le dossier où il devrait être. Vous devriez probablement parler de ce sujet à @Sangigi_Fuchsia sur twitter, car la faute provient probablement du serveur.");
  }

  if (!header('Location: share/' . $newname)) {
    throw new Exception("Echec de la redirection.");
  }
}
//Catch errors and output them
catch (Exception $e) {
  echo $e->getMessage();
}
