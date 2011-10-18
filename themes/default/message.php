<?php if (!defined('PATH')) exit('Incorrect access attempt.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
		<title><?php get_title(); ?></title>
		<link rel="stylesheet" type="text/css" href="<?php theme_url(); ?>style.css" />
	</head>
	<body>
		<div id="header">
			<a href="<?php echo base_path(); ?>" id="logo"><span><?php echo config_item('nom_wiki'); ?></span></a>
		</div>
			
		<div id="menu">
			<ul>
				<li><a href="<?php echo base_path(); ?>">Accueil</a></li>
				<li><a href="<?php echo base_path(); ?>index.php?a=liste" accesskey="l">Liste des pages</a></li>
				<li><a href="<?php echo base_path(); ?>index.php?a=modifications-recentes">Modifications récentes</a></li>
			</ul>
		</div>
		
		<div id="content">
			<h1><?php echo $heading; ?></h1>
			<p><?php echo $message; ?></p>
			<?php if ($back_index) : ?><p>
				Revenir à <a href="<?php echo base_path(); ?>">l'accueil</a>
			</p><?php endif; ?>

		</div>
		
		<div id="footer">
			<div id="copyright"><a href="http://wikeasy.dicssy.net/"><img src="<?php theme_url(); ?>wikeasy.png" /></a></div>
			
			<?php if ($_SESSION['wik_connect']) : ?><p class="bloc_admin">
				<a href="index.php?a=parametres">Paramètres</a> | <a href="index.php?a=suppressions">Suppressions</a> | 
				<a href="index.php?a=redirections">Redirections</a> | <a href="index.php?a=deconnexion">Déconnexion</a>
			</p>
			<?php else : ?><p class="lien_connexion">
				<a href="index.php?a=connexion">Connexion</a>
			</p><?php endif; ?>

		</div>
	</body>
</html>