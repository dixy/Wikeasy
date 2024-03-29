<?php
/**
 *	Wikeasy - http://wikeasy.dicssy.net
 *	Copyright (c) 2011  dixy <wikeasy@dicssy.net>
 *	Licensed under the GNU GPL license. See http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

define('RETURN_VAL', TRUE); //Permet de retourner une valeur au lieu de l'afficher

define('DT_HOUR', TRUE); //Permet d'afficher l'heure avec une date
define('DT_INTERVAL', TRUE); //Permet d'afficher un intervalle de temps plutôt qu'une date

define('CREATE_CACHE', TRUE); //Indique qu'on veut créer le fichier de cache plutôt que le récupérer.

/**
 *	Retourne le titre de l'article à mettre dans une URL, à partir d'un
 *	titre étant déjà passé à la fonction `clean_url`.
 */
function art_title2url($title)
{
	return str_replace(' ', '_', $title);
}

/**
 *	Retourne le titre à afficher de l'article à partir d'un titre étant déjà,
 *	passé à la fonction `clean_title`.
 */
function art_title($name)
{
	return str_replace('_', ' ', $name);
}

/**
 *	Supprime tous les caractères non autorisés du titre de l'article.
 *	Ces deux expressions rationnelles proviennent de MediaWiki 1.16.
 */
function clean_title($url)
{
	if ($url[0] == ':')
		$url = substr($url, 1);
	
	$url = preg_replace('/[ _\xA0\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', '_', $url);
	$url = preg_replace('/[^ %!"$&\'()*,\\-.\\/0-9:;=?@A-Z\\\\^_`a-z~\\x80-\\xFF+]/S', '_', $url);
	
	return ucfirst(trim($url, '_'));
}

/**
 *	Retourne les informations sur une page.
 *
 *	@param string $nom  Nom de la page, si elle n'existe pas retourne des valeurs par défaut.
 *  @param string $namespace
 */
function get_page($name, $namespace = '')
{
	if ($namespace == '')
		$namespace = config_item('namespace_defaut');
	
	$return = array(
		'name' => $name,
		'title' => art_title($name),
		'content' => '',
		'pageurl' => pageurl(ns_name($namespace).art_title2url($name)),
		'status' => 'public',
		'lastmodif' => '',
		'lastversion' => 0,
		'categories' => array(),
		'page_exists' => FALSE
	);
	
	$filename = PATH_PG.$namespace.'/'.$name.'.txt';
	if (is_file($filename))
	{
		$return = get_serialized($filename);
		$return['page_exists'] = TRUE;
		$return['pageurl'] = base_path().pageurl(ns_name($namespace).art_title2url($return['title']));
	}
	
	return $return;
}

/**
 *	Créé le fichier d'un article.
 *	Si l'article existait déjà il sera écrasé.
 */
function create_file($page, $ns = '', $noparse = FALSE, $createrevision = TRUE, $savelast = TRUE)
{
	if ($ns == '')
		$ns = config_item('namespace_defaut');
	
	if (!is_dir(PATH_CNT.'historique/'.$ns.'/'.$page['name']))
		mkdir(PATH_CNT.'historique/'.$ns.'/'.$page['name'], 0777);
	
	if (substr($page['content'], 0, 9) == '#REDIRECT')
		if (preg_match('`#REDIRECT\s*\[\[([^\[]+)]]`i', $page['content'], $r))
			$page['redirect'] = ns_name($ns).art_title2url(clean_title($r[1]));
	
	if ($createrevision)
	{
		$page['lastversion'] += 1;
		if ($savelast) save_last_change($page['title'], $ns, $page['lastversion']);
		write_file(PATH_CNT.'historique/'.$ns.'/'.$page['name'].'/'.$page['lastversion'].'.txt', $page['content']);
	}
	
	if (!$noparse)
		$page['content'] = parsewiki($page['content']);
	
	$test = write_file(PATH_PG.$ns.'/'.$page['name'].'.txt', serialize($page));
	
	if (!isset($page['redirect']))
	{
		$categories = reset_page_categories(cache_pages_categories(), $ns, $page['name']);
		foreach ($page['categories'] as $cat)
			if (!in_array($page['title'], $categories[$cat]))
				$categories[$cat][] = $page['title'];
		write_file(PATH_CACHE.'pages_categories', serialize($categories));
	}
	else
		generate_cache_redirects();
	
	return $test;
}

/**
 *	Vérifie si l'utilisateur peut modifier une page.
 */
function check_access($page)
{
	return !($page['status'] == 'private' || config_item('proteger_pages')) || $_SESSION['wik_connect'];
}

/**
 *	Redirection.
 */
function redirect($url = '')
{
	if ($url == '')
		$url = base_path();
	
	header('location: '.$url);
	exit;
}

/**
 *	Créé un fichier.
 */
function write_file($name, $content = '')
{
	if (!is_writable(dirname($name))) return FALSE;
	$ok = FALSE;
	
	if ($handle = fopen($name, 'w+'))
	{
		if (flock($handle, LOCK_EX))
		{
			$ok = fwrite($handle, trim($content));
			flock($handle, LOCK_UN);
		}
		
		fclose($handle);
	}
	
	return (is_file($name) && $ok);
}

/**
 *	Créé le fichier de cache contenant la liste des pages.
 *  
 *  @param string $namespace     Espace de nom dans lequel il faut générer le fichier de cache.
 *  @param string $pagename      Si un titre de page est renseigné et que le fichier existe, se contente 
 *      de vérifier si cette page est déjà dans le cache au lieu de recharger toutes les pages.
 */
function generate_cache_list($namespace, $pagename = '')
{
    $cachefile = PATH_CACHE.$namespace.'_pages';
	$pages = array();
	
	if ($pagename != '' && is_file($cachefile))
	{
		if (array_key_exists('redirect', get_serialized(PATH_PG.$namespace.'/'.$pagename.'.txt')))
			return;
		
		$pages = get_serialized($cachefile);
		if (!in_array($pagename, $pages))
			$pages[] = $pagename;
		else
			return;
	}
	else
	{
		$dir = dir(PATH_PG.$namespace);
		while (($page = $dir->read()) !== FALSE)
		{
			if ($page[0] != '.')
			{
				$page_content = get_serialized(PATH_PG.$namespace.'/'.$page);
				if (!isset($page_content['redirect']))
					$pages[] = $page_content['title'];
			}
		}
		$dir->close();
	}
	
	natcasesort($pages);
		
	write_file($cachefile, serialize($pages));
}

/**
 *  Créé le fichier de cache contenant la liste des redirections par namespace.
 */
function generate_cache_redirects()
{
	$redirects = array();
	
	if ($ns_dir = dir(PATH_PG))
	{
		while (($ns = $ns_dir->read()) !== FALSE)
		{
			if ($ns[0] != '.' && $pages_dir = dir(PATH_PG.$ns))
			{
				$redirects[$ns] = array();
				
				while (($page = $pages_dir->read()) !== FALSE)
				{
					if ($page[0] != '.')
					{
						$page_content = get_serialized(PATH_PG.$ns.'/'.$page);
						if (isset($page_content['redirect']))
							$redirects[$ns][] = $page_content['title'];
					}
				}
				
				$pages_dir->close();
				natcasesort($redirects[$ns]);
			}
		}
		
		$ns_dir->close();
	}
	
	write_file(PATH_CACHE.'liste_redirections', serialize($redirects));
}

/**
 *	Défini le titre de la page.
 */
function set_title($title = '')
{
	static $stored_title = '';
	if (!empty($title)) $stored_title = $title;
	return $stored_title;
}

function get_title()
{
	$title = set_title();
	echo $title.($title != '' ? ' - ' : '').config_item('nom_wiki');
}

/**
 *	Installation du wiki.
 */
function install()
{
	$dossier = dirname(__FILE__).'/content';
	if (is_writable($dossier))
	{
		mkdir($dossier.'/pages', 0777);
		mkdir($dossier.'/cache', 0777);
		mkdir($dossier.'/historique', 0777);
		mkdir($dossier.'/suppressions', 0777);
		mkdir($dossier.'/pages/Principal', 0777);
		mkdir($dossier.'/pages/'.NS_CATEGORIES, 0777);
		mkdir($dossier.'/historique/Principal', 0777);
		mkdir($dossier.'/historique/'.NS_CATEGORIES, 0777);
		$config = array(
			'page_defaut' => 'Accueil', 'utilisateur' => 'admin', 'nom_wiki' => 'Wikeasy', 'theme' => 'default',
			'pageurl_type' => 'normal', 'salt' => uniqid(mt_rand(), TRUE), 'version' => VERSION, 
			'nombre_modifs_recentes' => 50, 'proteger_pages' => 0, 'namespace_defaut' => 'Principal');
		$config['motdepasse'] = hash('sha256', $config['salt'].'123456');
		write_file($dossier.'/config.php', '<?php /* '.serialize($config).' */');
		create_file(array(
			'name' => 'Accueil', 'title' => 'Accueil', 'content' => "L'installation s'est bien déroulée.".
			"\n\nVos identifiants de connexion sont __admin__ et __123456__.%%%\nVous pouvez modifier ".
			"ces informations en vous connectant.", 'categories' => array(),
			'status' => 'public', 'lastversion' => 0));
		redirect();
	}
	else
	{
		header('Content-type: text/plain; charset=utf-8');
		exit("Le dossier content du wiki n'est pas accessible en écriture.\nLe dossier doit avoir un ".
			 "chmod de 777 pour pouvoir créer le dossier contenant les pages ainsi que le fichier de configuration.");
	}
}

/**
 *	URL du thème utilisé.
 */
function theme_url($return = FALSE)
{
	if ($return) return 'themes/'.config_item('theme').'/';
	echo 'themes/'.config_item('theme').'/';
}

function verify_nonce($action, $nonce)
{
	return (create_nonce($action) == $nonce);
}

function create_nonce($action)
{
	$nonce_life = ceil(time() / 10800); //Valide pendant 3 heures.
	return sha1($nonce_life.config_item('salt').session_id().$action);
}

function base_path()
{
	static $base_path = '';
	if (empty($base_path))
	{
		$base_path = '/';
		if ($dir = trim(dirname($_SERVER['SCRIPT_NAME']), '\,/'))
			$base_path .= $dir.'/';
	}
	return $base_path;
}

function get_ip()
{
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif (isset($_SERVER['HTTP_CLIENT_IP']))
		return $_SERVER['HTTP_CLIENT_IP'];
	
	return $_SERVER['REMOTE_ADDR'];
}

/**
 *  Parse un texte en wiki.
 *  Vérifie d'abord si ce n'est pas une redirection.
 */
function parsewiki($text)
{
	if (substr($text, 0, 9) == '#REDIRECT')
	{
		preg_match('`#REDIRECT\s*\[\[([^\[]+)]]`i', $text, $r);
		return '<div class="redirect"><p class="descr">Page de redirection</p>'.
			   '<p class="link"><span>→</span> <a href="'.
			   pageurl(art_title2url(clean_title($r[1]))).'">'.
			   htmlspecialchars($r[1]).'</a></p></div>';
	}
	
	require_once PATH.'wikirenderer.lib.php';
	
	$wiki = new WikiRenderer();
	return $wiki->render($text);
}

/**
 *	Retourne l'url de la page en fonction de la configuration.
 */
function pageurl($normal, $rewrite = '')
{
	if (config_item('pageurl_type') == 'rewrite' && $rewrite)
		return $rewrite;
	
	if (config_item('pageurl_type') != 'rewrite' && !$rewrite)
		return 'index.php?page='.$normal;
	
	return $normal;
}

/**
 *	Enregistre la modification qui vient d'être effectuée.
 */
function save_last_change($pagetitle, $namespace = '', $version = 0, $special = array())
{
	if (!is_file(PATH_CACHE.'modifications_recentes'))
		$recentchanges = array();
	else
		$recentchanges = get_serialized(PATH_CACHE.'modifications_recentes');
	
	$time = time();
	
	$new = array(
		'pagetitle' => $pagetitle,
		'date' => format_date($time),
		'hour' => date('H\hi', $time),
		'namespace' => $namespace);
	
	if ($version > 0) $new['version'] = $version;
	if (isset($special['oldname'])) $new['oldname'] = $special['oldname'];
	elseif (isset($special['delete'])) $new['delete'] = TRUE;
	elseif (isset($special['undelete'])) $new['undelete'] = TRUE;
	
	array_unshift($recentchanges, $new);
	
	if (count($recentchanges) > config_item('nombre_modifs_recentes'))
		array_pop($recentchanges);
	
	write_file(PATH_CACHE.'modifications_recentes', serialize($recentchanges));
}

/**
 *	Formate une date.
 */
function format_date($date, $hour = FALSE, $interval = FALSE)
{
	if (empty($date))
		return 'Aucune information';
		
	$str_month = array(
		'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 
		'octobre', 'novembre', 'décembre');
	
	if ($interval)
	{
		$diff = time() - $date;
		if ($diff < 60)
			return 'il y a '.$diff.'s';
		elseif ($diff < 3600)
			return 'il y a '.(int)date('i', $diff).' min';
		elseif ($diff < 3600 * 2)
			return 'il y a '.(int)date('h', $diff - 3600).'h'.date('i', $diff);
	}
	
	$j = date('j', $date);
	return ($j == 1 ? '1<sup>er</sup>' : $j).' '.$str_month[date('n', $date)-1].' '.date('Y', $date).($hour ? ' à '.date('H\hi', $date) : '');
}

/**
 *	Supprime tout l'historique d'un article.
 *	@param string $page		Nom de la page.
 *	@param bool   $dirtoo	S'il faut aussi supprimer le dossier.
 */
function delete_history($page, $namespace, $dirtoo = TRUE)
{
	$path_dir = PATH_CNT.'historique/'.$namespace.'/'.$page;
	
	if (!is_dir($path_dir)) return FALSE;
	
	$dir = dir($path_dir);
	while (($vers = $dir->read()) !== FALSE)
		if ($vers[0] != '.')
			unlink($path_dir.'/'.$vers);
	$dir->close();
	
	if ($dirtoo) rmdir($path_dir);
	return is_dir($path_dir);
}

/**
 *	Retourne un mot au singulier ou pluriel suivant le nombre $nbr
 *	@param int    $nbr	Nombre à tester
 *	@param string $sing	Singulier du mot ou de la phrase
 *	@param string $plu	Pluriel à retourner (facultatif)
 */
function plural($nbr, $sing, $plu = NULL)
{
	if ($nbr > 1)
		return isset($plu) ? $plu : $sing.'s';
	
	return $sing;
}

/**
 *	Créé le fichier contenant la liste des articles supprimés.
 */
function generate_deleted_articles_cache()
{
	$deleted = array();
	
	if ($dir = dir(PATH_CNT.'suppressions'))
	{
		while (($ns = $dir->read()) !== FALSE)
		{
			if ($ns[0] != '.' && is_dir($dir->path.'/'.$ns) && $nsdir = dir($dir->path.'/'.$ns))
			{
				while (($art = $nsdir->read()) !== FALSE)
					if ($art[0] != '.')
						$deleted[ns_name($ns).$art] = $ns.'/'.$art;
				$nsdir->close();
			}
		}
		$dir->close();
	}
	
	write_file(PATH_CACHE.'liste_supprimes', serialize($deleted));
}

/**
 *	Liste des articles supprimés.
 */
function deleted_articles()
{
	if (!is_file(PATH_CACHE.'liste_supprimes'))
		generate_deleted_articles_cache();
	
	return get_serialized(PATH_CACHE.'liste_supprimes');
}

/**
 *	Retourne le paramètre de configuration demandé
 *	
 *	@param string $item   Nom du paramètre recherché
 *	@param string $newval Permet de modifier la valeur du paramètre
 */
function config_item($item = '', $newval = '')
{
	static $_config = array();
	
	if (empty($_config) && is_file(PATH_CNT.'config.php'))
	{
		$_config = unserialize(substr(file_get_contents(PATH_CNT.'config.php'), 9, -3));
	}
	
	if ($item != '' && $newval !== '')
	{
		if (isset($_config[$item]))
		{
			$_config[$item] = $newval;
		}
	}
	
	if ($item == '')
		return $_config;
	elseif (isset($_config[$item]))
		return $_config[$item];
	else
		return FALSE;
}

/**
 *  Affiche une page avec un message.
 */
function show_error($message, $heading = 'Erreur', $back_index = TRUE)
{
	set_title($heading);
	require PATH.theme_url(RETURN_VAL).'message.php';
	exit;
}

/**
 *  Création du fichier de cache de la liste des catégories.
 */
function cache_categories()
{
	$file = PATH_CACHE.NS_CATEGORIES.'_pages';
	
	if (!is_file($file))
		generate_cache_list(NS_CATEGORIES);
	
	return get_serialized($file);
}

/**
 *  Création du fichier de cache faisant le lien entre les pages et les catégories.
 *  
 *  @param bool $create  S'il faut créer le fichier de cache au lieu de le retourner.
 */
function cache_pages_categories($create = FALSE)
{
	$file = PATH_CACHE.'pages_categories';
	
	if ($create == CREATE_CACHE)
	{
		$cats = array();
		
		$dir = dir(PATH_PG.config_item('namespace_defaut'));
		while (($page = $dir->read()) !== FALSE)
		{
			if ($page[0] != '.')
			{
				$contenu = get_serialized($dir->path.'/'.$page);
				foreach ($contenu['categories'] as $c)
				{
					if (!isset($contenu['redirect']))
					{
						if (!isset($cats[$c]))
							$cats[$c] = array();
						$cats[$c][] = $contenu['title'];
					}
				}
			}
		}
		$dir->close();
	
		write_file($file, serialize($cats));
	}
	else
	{
		if (!is_file($file))
			cache_pages_categories(CREATE_CACHE);
		
		return get_serialized($file);
	}
}

/**
 *  Retourne le tableau contenant l'association catégories <> pages, en retirant
 *  la page de toutes les catégories dans lesquelles elle est actuellement.
 *
 *  @param array $categories
 *  @param string $namespace
 *  @param string $page
 */
function reset_page_categories($categories, $namespace, $pagename)
{
	$page = get_serialized(PATH_PG.$namespace.'/'.$pagename.'.txt');
	
	foreach ($page['categories'] as $cat)
	{
		$key = array_search($page['title'], $categories[$cat]);
		unset($categories[$cat][$key]);
	}
	
	return $categories;
}

/**
 *  Retourne "namespace:" s'il est différent du namespace par défaut.
 */
function ns_name($ns)
{
	return ($ns != config_item('namespace_defaut') ? $ns.':' : '');
}

/**
 *  Retourne un tableau à partir d'un tableau linéarisé.
 */
function get_serialized($file)
{
	return unserialize(file_get_contents($file));
}

/* End of file functions.php */