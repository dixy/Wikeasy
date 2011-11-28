<?php
/*
*	Wikeasy - http://wikeasy.dicssy.net
*	Copyright (c) 2011  dixy <wikeasy@dicssy.net>
*	Licensed under the GNU GPL license. See http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

error_reporting(E_ALL);
define('VERSION', '0.5-dev');

/*
*	Définition des constantes des chemins.
*
*	PATH		Chemin absolu du script
*	PATH_PG		Chemin absolu du dossier contenant les pages.
*/

define('PATH', 		dirname(__FILE__).'/');
define('PATH_CNT',	PATH.'content/');
define('PATH_PG',	PATH_CNT.'pages/');
define('PATH_CACHE',PATH_CNT.'cache/');


/*
*	Chargement des fonctions.
*/

require PATH.'functions.php';


/*
*	Installation du wiki s'il ne l'est pas.
*/

if (!is_dir(PATH_PG) || !is_file(PATH_CNT.'config.php'))
	install();


/*
*	Initalisation.
*/

header('Content-Type: text/html; charset=utf-8');

session_start();

if (get_magic_quotes_gpc())
{
	function stripslashes_array($array) {
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}
	
	$_GET 	= stripslashes_array($_GET);
	$_POST 	= stripslashes_array($_POST);
}

if (!isset($_SESSION['wik_connect'])) $_SESSION['wik_connect'] = FALSE;

if (!ini_get('date.timezone'))
	date_default_timezone_set('Europe/Paris');

mb_internal_encoding('UTF-8');

$and = pageurl('&amp;', '?');


/*
*	Définition de l'action à effectuer.
*/

$speciales = array('modifier', 'connexion', 'parametres', 'deconnexion', 'liste', 'supprimer', 'historique', 
				   'modifications-recentes', 'renommer', 'redirections', 'suppressions');
$mode = (!empty($_GET['a']) && in_array($_GET['a'], $speciales)) ? $_GET['a'] : 'lire';

if (in_array($mode, array('lire', 'modifier', 'supprimer', 'historique', 'renommer')))
{
	$namespace = config_item('namespace_defaut');
	
	if (!empty($_GET['page'])) $page_name = $_GET['page'];
	else $page_name = config_item('page_defaut');
	
	if (($cleaned_title = clean_title($page_name)) != $page_name)
	{
		header('HTTP/1.1 301 Moved Permanently');
		redirect(base_path().pageurl($cleaned_title));
	}
	
	if (mb_strlen($cleaned_title) < 2 || mb_strlen($cleaned_title) > 64)
		show_error('Le titre de l\'article est soit trop court, soit trop long (maximum 64 caractères).');
	
	if (mb_strpos($cleaned_title, ':'))
	{
		if (preg_match('`^[[:alpha:]]{2,}$`u', mb_substr($cleaned_title, 0, mb_strpos($cleaned_title, ':')), $ns))
		{
			if (!is_dir(PATH_PG.$ns[0]))
				show_error('Cet espace de nom n\'existe pas.');
			else
			{
				$namespace = $ns[0];
				$cleaned_title = mb_substr($cleaned_title, mb_strpos($cleaned_title, ':') + 1);
			}
		}
	}
	
	$page = get_page($cleaned_title, $namespace);
}


/*
*	Vérification des autorisations.
*/

if (!$_SESSION['wik_connect'] && in_array($mode, array('parametres', 'deconnexion', 'supprimer', 'renommer', 'suppressions')))
{
	header('HTTP/1.1 401 Unauthorized');
	redirect(base_path().'index.php?a=connexion');
}


/*
*	Gestion des actions.
*/

if ($mode == 'modifier')
{
	$page_to_edit = $page['content'];
	if ($page['lastversion'] > 0 && is_file(PATH_CNT.'historique/'.$namespace.'/'.$page['name'].'/'.$page['lastversion'].'.txt'))
		$page_to_edit = file_get_contents(PATH_CNT.'historique/'.$namespace.'/'.$page['name'].'/'.$page['lastversion'].'.txt');
	
	if (check_access($page))
	{
		set_title(($page['page_exists'] ? 'Modification' : 'Création').' de "'.$page['title'].'"');
		$categories = cache_categories();
		
		if ($namespace == 'Catégorie')
			unset($categories[$page['name']]);
		
		if (!empty($_GET['r']))
		{
			$pathfile = PATH_CNT.'historique/'.$namespace.'/'.$page['name'].'/'.(int)$_GET['r'].'.txt';
			if (is_file($pathfile))
			{
				if ($_GET['r'] != $page['lastversion'])
				{
					$erreur = 'Vous êtes entrain de modifier la version de cette page du '.
								format_date(filemtime($pathfile), DT_HOUR);
					$page_to_edit = file_get_contents($pathfile);
				}
			} else $erreur = 'La version à restaurer n\'existe pas.';
		}
		
		if (isset($_POST['contenu_page']))
		{
			if (!empty($_POST['contenu_page']) && mb_strlen(trim($_POST['contenu_page'])) >= 1)
			{
				$page['categories'] = array();
				if (!empty($_POST['categories_page']) && is_array($_POST['categories_page']))
					$page['categories'] = array_unique(array_filter($_POST['categories_page'], 
						function ($c) use ($categories) { return isset($categories[$c]); }));
				
				
				if (!empty($_POST['ajout_categorie']))
				{
					if (!empty($_POST['ajout_cat_nom']))
					{
						if (isset($categories[$_POST['ajout_cat_nom']]))
						{
							if (!in_array($_POST['ajout_cat_nom'], $page['categories']))
							{
								$page['categories'][] = $_POST['ajout_cat_nom'];
							}
						}
						else $erreur = 'La catégorie choisie n\'existe pas.';
					}
					else $erreur = 'Vous devez choisir une catégorie.';
				}
				elseif (!empty($_POST['previ_page']))
					$apercu_page = parsewiki($_POST['contenu_page']);
				elseif (!empty($_POST['enreg_page']))
				{
					$page['content'] = trim($_POST['contenu_page']);
					
					if ($_SESSION['wik_connect'])
						$page['status'] = !empty($_POST['change_status']) ? 'private' : 'public';
					
					if (create_file($page, $namespace))
					{
						if ($namespace == 'Catégorie')
							cache_categories(CREATE_CACHE);
						generate_cache_list($namespace);
						redirect($page['pageurl'].(isset($page['redirect']) ? pageurl('&', '?').'redirect=no' : ''));
					}
					
					$erreur = 'Erreur lors de la création du fichier de la page.';
				}
				
				$page_to_edit = $_POST['contenu_page'];
			}
			else $erreur = 'Le contenu de l\'article est trop court.';
		}
	}
	else
		set_title('Voir le texte source de "'.$page['title'].'"');
}
elseif ($mode == 'connexion')
{
	set_title('Connexion');
	
	if (!empty($_POST['util']) && !empty($_POST['mdp']))
	{
		if ($_POST['util'] == config_item('utilisateur') && hash('sha256', config_item('salt').$_POST['mdp']) == config_item('motdepasse'))
		{
			$_SESSION['wik_connect'] = TRUE;
			redirect();
		}
		else $erreur = 'Nom d\'utilisateur et/ou mot de passe incorrect.';
	}
}
elseif ($mode == 'deconnexion')
{
	$_SESSION = array();
	session_destroy();
	redirect();
}
elseif ($mode == 'parametres')
{
	set_title('Paramètres du wiki');
	
	$liste_themes = array();
	$dir = opendir(PATH.'themes');
	while ($theme = readdir($dir))
		if ($theme[0] != '.' && is_file(PATH.'themes/'.$theme.'/template.php'))
			$liste_themes[] = $theme;
	closedir($dir);
	
	$rewrite_status = FALSE;
	if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) $rewrite_status = TRUE;
	if (!$rewrite_status)
	{
		ob_start(); phpinfo(); $r = ob_get_clean();
		if (strpos($r, 'mod_rewrite') !== FALSE) $rewrite_status = TRUE;
	}
	
	if (!empty($_POST['config']) && !empty($_POST['_cfgnonce']))
	{
		if (verify_nonce('modify-configuration', $_POST['_cfgnonce']))
		{
			if (!empty($_POST['config']['motdepasse']) && strlen(trim($_POST['config']['motdepasse'])) >= 6)
				config_item('motdepasse', hash('sha256', config_item('salt').$_POST['config']['motdepasse']));
			
			if (empty($_POST['config']['theme']) || !in_array($_POST['config']['theme'], $liste_themes))
				$_POST['config']['theme'] = config_item('theme');
			
			$_POST['config']['nombre_modifs_recentes'] = intval($_POST['config']['nombre_modifs_recentes']);
			$_POST['config']['proteger_pages'] = isset($_POST['config']['proteger_pages']) ? 1 : 0;
			
			foreach (config_item() as $key => $value)
				if (isset($_POST['config'][$key]) && $key != 'motdepasse')
					config_item($key, $_POST['config'][$key]);
			
			if (config_item('pageurl_type') == 'rewrite' && is_file(PATH.'htaccess.txt'))
				rename(PATH.'htaccess.txt', PATH.'.htaccess');
			elseif (config_item('pageurl_type') == 'normal' && is_file(PATH.'.htaccess'))
				rename(PATH.'.htaccess', PATH.'htaccess.txt');
			
			$contenu_config = '<?php $config = '.var_export(config_item(), TRUE).'; ?>';
			
			if (write_file(PATH_CNT.'config.php', $contenu_config))
				$erreur = 'Configuration modifiée';
			else
				$erreur = 'Erreur lors de la modification du fichier de configuration';
		}
		else $erreur = 'L\'opération n\'a pas pu être validée.';
	}
}
elseif ($mode == 'liste')
{
	set_title('Liste des articles');
	
	$ns = config_item('namespace_defaut');
	
	if (!is_file(PATH_CACHE.$ns.'_articles.php'))
		generate_cache_list($ns);
	
	require PATH_CACHE.$ns.'_articles.php';
}
elseif ($mode == 'historique')
{
	if (!$page['page_exists'])
		redirect($page['pageurl']);
	
	set_title('Historique des versions de « '.$page['title'].' »');
	
	$versions = array();
	$path = PATH_CNT.'historique/'.$namespace.'/'.$page['name'];
	$dir = opendir($path);
	while ($vers = readdir($dir))
		if ($vers[0] != '.')
			$versions[substr($vers, 0, strpos($vers, '.'))] = format_date(filemtime($path.'/'.$vers), DT_HOUR);
	closedir($dir);
	
	krsort($versions);
	
	if (!empty($_GET['s']) && isset($versions[$_GET['s']]))
		$see_content = parsewiki(file_get_contents($path.'/'.(int)$_GET['s'].'.txt'));
	
	if (!empty($_REQUEST['oldid']) && !empty($_REQUEST['recentid']))
	{
		$oldid = (int)$_REQUEST['oldid'];
		$recentid = (int)$_REQUEST['recentid'];
		
		if (isset($versions[$oldid], $versions[$recentid]))
		{
			if ($oldid != $recentid)
			{
				require PATH.'diffengine.php';
				$diff_result = new Diff(explode("\n", file_get_contents($path.'/'.$oldid.'.txt')), 
										explode("\n", file_get_contents($path.'/'.$recentid.'.txt')));
				$formatter = new WikeasyDiffFormatter();
				
				$diff = array(
					'rows' => $formatter->format($diff_result),
					'old_date' => $versions[$oldid],
					'new_date' => $versions[$recentid]);
			} else $erreur = 'Sélectionnez deux versions différentes.';
		} else $erreur = 'Une des deux versions sélectionnée n\'existe pas.';
	}
}
elseif ($mode == 'modifications-recentes')
{
	set_title('Modifications récentes');
	
	$recentchanges = array();
	if (is_file(PATH_CACHE.'modifications_recentes'))
		$recentchanges = unserialize(file_get_contents(PATH_CACHE.'modifications_recentes'));
}
elseif ($mode == 'renommer')
{
	if (!$page['page_exists'])
		redirect($page['pageurl']);
	
	if (!empty($_POST['page_newtitle']) && !empty($_POST['_rennonce']))
	{
		if (verify_nonce('rename-page', $_POST['_rennonce']))
		{
			$new_name = clean_title(trim($_POST['page_newtitle']));
			
			if ($new_name == $page['name'])
				redirect($page['pageurl']);
			
			if (mb_strlen($new_name) >= 2 && mb_strlen($new_name) <= 64)
			{
				if (!is_file(PATH_PG.$namespace.'/'.$new_name.'.txt'))
				{
					$oldpage = $page;
					
					$t1 = rename(PATH_CNT.'historique/'.$namespace.'/'.$page['name'], PATH_CNT.'historique/'.$namespace.'/'.$new_name);
					$t2 = rename(PATH_PG.$namespace.'/'.$page['name'].'.txt', PATH_PG.$namespace.'/'.$new_name.'.txt');
					
					if ($t1 && $t2)
					{
						$page['name'] = $new_name;
						$page['title'] = art_title($new_name);
						create_file($page, $namespace, TRUE, FALSE);
						
						$oldpage['lastversion'] = 0;
						$oldpage['content'] = '#REDIRECT [['.$page['title'].']]';
						create_file($oldpage, FALSE, TRUE, FALSE);
						
						save_last_change($page['title'], $namespace, 0, array('oldname' => $oldpage['title']));
						
						generate_cache_list($namespace);
						redirect(base_path().pageurl(($namespace != config_item('namespace_defaut') ? $namespace.':' : '').art_title2url($page['title'])));
					} else $erreur = 'Erreur lors du renommage de l\'article.';
				} else $erreur = 'Le nouveau titre choisi est déjà utilisé.';
			} else $erreur = 'Le nouveau titre est soit trop court soit trop long (maximum 64 caractères).';
		} else $erreur = 'Opération incorrecte.';
	}
	
	set_title('Renommer '.$page['title']);
}
elseif ($mode == 'redirections')
{
	set_title('Liste des redirections');
	
	if (!is_file(PATH_CACHE.'liste_redirections.php'))
		generate_cache_list($namespace);
	
	require PATH_CACHE.'liste_redirections.php';
}
elseif ($mode == 'supprimer')
{
	if (!$page['page_exists']) redirect($page['pageurl']);
	
	if (!empty($_POST['suppr_ok']) && !empty($_POST['_delnonce']))
	{
		if (verify_nonce('delete-page', $_POST['_delnonce']))
		{
			if (!is_dir(PATH_CNT.'suppressions/'.$namespace))
				mkdir(PATH_CNT.'suppressions/'.$namespace, 0777);
			
			$pagedeleted = array(
				'title' => $page['title'],
				'content' => file_get_contents(PATH_CNT.'historique/'.$namespace.'/'.$page['name'].'/'.$page['lastversion'].'.txt'),
				'deletetime' => time(),
				'isredirect' => isset($page['redirect']),
				'namespace' => $namespace,
				'categories' => $page['categories']);
			
			if (write_file(PATH_CNT.'suppressions/'.$namespace.'/'.$page['name'], serialize($pagedeleted)))
			{
				delete_history($page['name'], $namespace);
				unlink(PATH_PG.$namespace.'/'.$page['name'].'.txt');
				
				if ($namespace == 'Catégorie')
					cache_categories(CREATE_CACHE);
				
				save_last_change($page['title'], $namespace, 0, array('delete' => 1));
				generate_cache_list($namespace);
				generate_deleted_articles_cache();
				redirect();
			}
			else $erreur = 'Erreur lors de la création du fichier de restauration.';
		}
		else $erreur = 'L\'opération n\'a pas pu être validée.';
	}
	
	set_title('Supprimer '.$page['title']);
}
elseif ($mode == 'suppressions')
{
	set_title('Liste des pages supprimées');
	
	$pages_deleted = deleted_articles();
	
	foreach ($pages_deleted as $name => &$infos)
		if (is_file(PATH_CNT.'suppressions/'.$infos))
			$infos = unserialize(file_get_contents(PATH_CNT.'suppressions/'.$infos));
	
	if (!empty($_GET['r']))
	{
		if (isset($pages_deleted[$_GET['r']]))
		{
			set_title('Restaurer une page supprimée');
			
			$undelete = $pages_deleted[$_GET['r']];
			$check_exists = is_file(PATH_PG.$undelete['namespace'].'/'.$_GET['r'].'.txt');
			
			if (!empty($_POST['_undelnonce']) && !empty($_POST['undelete_ok']) && (!$check_exists || !empty($_POST['undel_act'])))
			{
				if (verify_nonce('undelete-page', $_POST['_undelnonce']))
				{
					$new_title = !empty($_POST['new_title']) ? clean_title($_POST['new_title']) : '';
					if ($check_exists)
						$act = $_POST['undel_act'] == 'delete' ? 'delete' : 'new';
					
					if (!$check_exists || (($act == 'new' && !empty($new_title) && !is_file(PATH_PG.$undelete['namespace'].'/'.$new_title.'.txt')) || $act == 'delete'))
					{
						if ($check_exists && $act == 'delete')
							delete_history(art_title2url($undelete['title']), $namespace, FALSE);
					
						$page = array(
							'name' => ($check_exists && $act == 'new') ? $new_title : art_title2url($undelete['title']),
							'title' => ($check_exists && $act == 'new') ? art_title($new_title) : $undelete['title'],
							'content' => $undelete['content'],
							'status' => 'public',
							'lastversion' => 0,
							'categories' => $undelete['categories']);
						
						create_file($page, $undelete['namespace'], FALSE, TRUE, FALSE);
						save_last_change($page['title'], $undelete['namespace'], 0, array('undelete' => 1));
						
						unlink(PATH_CNT.'suppressions/'.$undelete['namespace'].'/'.art_title2url($undelete['title']));
						
						generate_cache_list($undelete['namespace']);
						generate_deleted_articles_cache();
						
						if ($undelete['namespace'] == 'Catégorie')
							cache_categories(CREATE_CACHE);
						
						redirect(base_path().
								pageurl(($undelete['namespace'] != config_item('namespace_defaut') ? $undelete['namespace'].':' : '').art_title2url($page['name'])).
								($undelete['isredirect'] ? pageurl('&', '?').'redirect=no' : ''));
					}
					else $erreur = 'Le nom de l\'article n\'a pas été renseigné, ou un article du même nom existe déjà.';
				}
				else $erreur = 'Opération invalide.';
			}
		}
		else $erreur = 'Cet article n\'est pas dans les articles supprimés.';
	}
}
elseif ($mode == 'lire')
{
	if (isset($page['redirect']) && (!isset($_GET['redirect']) || $_GET['redirect'] != 'no'))
	{
		header('HTTP/1.1 301 Moved Permanently');
		redirect(base_path().pageurl($page['redirect']));
	}
	
	if (!$page['page_exists'])
	{
		header('HTTP/1.1 404 Not Found');
		$was_deleted = array_key_exists($page['name'], deleted_articles());
	}
	
	set_title(($namespace != config_item('namespace_defaut') ? $namespace.':' : '').$page['title']);
}


/*
*	Affichage de la page.
*/

require PATH.theme_url(RETURN_VAL).'template.php';

/* End of file index.php */