<?php
/*
*	Wikeasy - http://wikeasy.dicssy.net
*	Copyright (c) 2011  dixy <wikeasy@dicssy.net>
*	Licensed under the GNU GPL license. See http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*
*	Constantes utiles et chargement des fonctions.
*/

define('VERSION', '0.5');

define('PATH',	  dirname(__FILE__).'/');
define('PATH_CNT',  PATH.'content/');
define('PATH_PG',   PATH_CNT.'pages/');
define('PATH_CACHE',PATH_CNT.'cache/');

require PATH.'functions.php';

/*
*	Fonctions pour la mise à jour.
*/

function up_template($titre, $contenu)
{
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
		<title><?php echo $titre; ?></title>
		<style type="text/css">
		body{padding:0;margin:0;font:0.7em Tahoma,sans-serif;line-height:1.5em;background:#fff;color:#454545;}
		a{color:#e0691a;}a:hover{color:#6c757a;}
		#header{background:#eee;border-bottom:1px solid #ccc;padding:10px;margin:0 0 10px;}
		h1{font:normal 2.1em Arial,Sans-Serif;letter-spacing:-1px;padding:7px 0 0 8px;margin:0;color:#737373;}
		#content{width:700px;margin:0 auto;}
		#content h2{background:#6c757a;color:#fff;padding:7px 0 7px 5px;font:bold 1em Tahoma,Arial,Sans-Serif;margin:0 0 3px;}
		#content #incontent{background:#f5f5f5;color:#414141;padding:8px;margin:0 0 3px;}
		</style>
	</head>
	<body>
		<div id="header"><h1>Wikeasy - Mise à jour</h1></div>
		<div id="content">
			<h2><?php echo $titre; ?></h2>
			<div id="incontent"><?php echo $contenu; ?></div>
		</div>
	</body>
</html><?php exit;
}

function write_config($config)
{
	$contenu_config = '<?php /* '.serialize($config).' */';
	
	if (!write_file(PATH_CNT.'config.php', $contenu_config))
		up_template('Erreur', 'Erreur lors de la modification du fichier de configuration. '.
				'Vérifiez que le chmod du dossier content est à 777.');
}

/*
*	Récupération de la configuration.
*/

if (!is_dir(PATH_PG) || !is_file(PATH_CNT.'config.php'))
	up_template('Erreur', 'Votre wiki n\'est pas installé. Exécutez index.php pour l\'installer.');

$_cfg = file_get_contents(PATH_CNT.'config.php');

if ($_cfg[6] == '/')
	$config = unserialize(substr($_cfg, 9, -3));
else
	require PATH_CNT.'config.php';
unset($_cfg);

if (!isset($config['version'])) $config['version'] = '0.2';

/*
*	Mise à jour.
*/

if ($config['version'] == VERSION)
	up_template('Information', 'Votre wiki est à jour.<br /><br /><a href="'.base_path().'">Index</a>');

$messages = array();
$possible_update = array('0.2', '0.3', '0.3.1', '0.4');
$current = $config['version'];

foreach ($possible_update as $k => $version)
{
	if ($version == $current)
	{
		$next_version = isset($possible_update[$k + 1]) ? $possible_update[$k + 1] : VERSION;
		$config['version'] = $next_version;
		$messages[] = call_user_func('up_'.str_replace('.', '', $current).'_to_'.str_replace('.', '', $next_version));
		write_config($config);
		$current = $next_version;
	}
}

/*
*	Toutes les mises à jour par version.
*/

function up_02_to_03()
{
	global $config;
	$config['nombre_modifs_recentes'] = 50;
	$config['motdepasse'] = hash('sha256', '123456');
	
	$dir = dir(PATH_PG);
	while (($p = $dir->read()) !== FALSE)
	{
		if (substr($p, -3) == 'xml')
		{
			$nom = substr($p, 0, -4);
			$page = get_page($nom);
			$page['content'] = file_get_contents(PATH_CNT.'historique/'.$nom.'/'.$page['lastversion'].'.txt');
			create_file($page, '', FALSE, FALSE);
		}
	}
	$dir->close();
	
	return '<strong>Attention</strong> Le mot de passe administrateur a été changé. Le nouveau mot de passe est <strong>123456</strong>.'.
			'Il est conseillé de le changer dès maintenant.';
}

function up_03_to_031() { }
function up_031_to_04() { mkdir(PATH_CNT.'suppressions', 0777); }

function up_04_to_05()
{
	global $config;
	$config['motdepasse'] = hash('sha256', $config['salt'].'123456');
	$config['namespace_defaut'] = 'Principal';
	$ns = 'Principal';
	
	mkdir(PATH_CNT.'cache', 0777);
	mkdir(PATH_PG.'Principal', 0777);
	mkdir(PATH_PG.'Catégorie', 0777);
	rename(PATH_CNT.'historique', PATH_CNT.'Principal');
	mkdir(PATH_CNT.'historique', 0777);
	rename(PATH_CNT.'Principal', PATH_CNT.'historique/Principal');
	mkdir(PATH_CNT.'historique/Catégorie', 0777);
	
	$recentchanges = array();
	if (is_file(PATH_CNT.'modifications_recentes.php'))
	{
		require PATH_CNT.'modifications_recentes.php';
		unlink(PATH_CNT.'modifications_recentes.php');
	}
	$recentchanges = array_reverse($recentchanges);
	foreach ($recentchanges as &$c)
		if (!isset($c['namespace']))
			$c['namespace'] = 'Principal';
	write_file(PATH_CACHE.'modifications_recentes', serialize($recentchanges));
	
	if (is_file(PATH_CNT.'liste_pages.php')) unlink(PATH_CNT.'liste_pages.php');
	if (is_file(PATH_CNT.'liste_redirections.php')) unlink(PATH_CNT.'liste_redirections.php');
	
	$dir = dir(PATH_PG);
	while (($p = $dir->read()) !== FALSE)
	{
		if ($p[0] != '.' && !is_dir($dir->path.'/'.$p))
		{
			$contenu_fichier = file_get_contents($dir->path.'/'.$p);
			
			$parser = xml_parser_create('UTF-8');
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
			xml_parse_into_struct($parser, $contenu_fichier, $valeurs, $index_tags);
			xml_parser_free($parser);
			
			$page = array(
				'name' => clean_title($valeurs[$index_tags['title'][0]]['value']),
				'title' => $valeurs[$index_tags['title'][0]]['value'],
				'content' => trim($valeurs[$index_tags['content'][0]]['value']),
				'lastmodif' => $valeurs[$index_tags['lastmodif'][0]]['value'],
				'status' => $valeurs[$index_tags['status'][0]]['value'],
				'lastversion' => (int)$valeurs[$index_tags['lastversion'][0]]['value'],
				'categories' => array()
			);
			
			if (isset($index_tags['redirectto']))
				$page['redirect'] = $valeurs[$index_tags['redirectto'][0]]['value'];
			
			if (!is_dir(PATH_CNT.'historique/'.$ns.'/'.$page['name']))
				mkdir(PATH_CNT.'historique/'.$ns.'/'.$page['name'], 0777);
			
			if (substr($page['content'], 0, 9) == '#REDIRECT')
				if (preg_match('`#REDIRECT\s*\[\[([^\[]+)]]`i', $page['content'], $r))
					$page['redirect'] = art_title2url(clean_title($r[1]));
			
			write_file(PATH_PG.$ns.'/'.$page['name'].'.txt', serialize($page));
			unlink($dir->path.'/'.$p);
			
			if (is_dir(PATH_CNT.'historique/'.$ns.'/'.substr($p, 0, -4)))
				rename(PATH_CNT.'historique/'.$ns.'/'.substr($p, 0, -4), PATH_CNT.'historique/'.$ns.'/'.$page['name']);
	   }
	}
	
	return '<strong>Attention</strong> Le mot de passe administrateur a été changé. Le nouveau mot de passe est <strong>123456</strong>. '.
			'Il est conseillé de le changer dès maintenant.';
}

/*
*	Fin de la maj.
*/

up_template('Information', 'Votre wiki a été mis à jour avec succès.<br /><br />'.
	(empty($messages) ? '' : implode('<br /><br />', $messages).'<br /><br />').'<a href="'.base_path().'">Index</a>');

/* End of file update.php */