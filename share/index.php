<?php

//Get filename from URL
if (isset($_GET["filename"]) && $_GET["filename"] != "" && $_GET["filename"] != " ") {
    $filename = htmlentities($_GET["filename"]);
}
//404 if no value
else {
    $filename = "404.png";
}

//404 if file doesn't exist
if (file_exists ("../file/" . $filename)) {
}
else {
    header("HTTP/1.0 404 Not Found");
    $filename = "404.png";
}

//Set the variables for the direct link
$filelocation = "../file/" . $filename;

//Find extensions to use for preview
$extension = explode('.', $filename);
$extension = strtolower(end($extension));

?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php echo $filename; ?> - Cyla</title>

    <meta charset="UTF-8">

    <!-- Twitter cards -->

    <?php
    include_once("../static/includes/cards.php")
    ?>

    <!-- OpenGraph for Facebook -->

    <?php
    include_once("../static/includes/opengraph.php")
    ?>

    <!-- Stylesheets -->

		<link rel="stylesheet" type="text/css" href="../static/CSS/view.css">
		<link rel="stylesheet" type="text/css" href="../static/colors.css">

    <?php
    include_once("../static/includes/video.php")
    ?>

	</head>
	<body>

    <!-- There, I use Piwik now, be happy botnet freaks -->
    <?php     include_once("../static/includes/botnet.php") ?>

		<h1 class="dltext">"<?php echo $filename;?>"</h1>

    <div class="buttoncontainer">

      <a href="<?php echo $filelocation;?>" target="_blank" >
      <div class="button">Voir/écouter/télécharger</div>
      </a>

      <a href="http://ersatz.xyz/yourls-api.php?signature=a2eefde33d&action=shorturl&format=simple&url=http://concepts.esenjin.xyz/cyla/v2/file/<?php echo $filename;?>" onclick="window.open(this.href, 'Popup', 'scrollbars=1,resizable=1,height=60,width=200'); return false;" target="_blank">
      <div class="button">Raccourcir l'url</div>
      </a>

      <a href="../">
      <div class="button">Héberger un autre fichier</div>
      </a>

    </div>

		<?php include_once("../static/includes/preview.php") ?>



	</body>
</html>
