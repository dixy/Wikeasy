<?php
/**
 *	Wikeasy - http://wikeasy.dicssy.net
 *	Copyright (c) 2011  dixy <wikeasy@dicssy.net>
 *	Licensed under the GNU GPL license. See http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

define('RETURN_VAL', TRUE); //Permet de retourner une valeur au lieu de l'afficher

define('DT_HOUR', TRUE); //Permet d'afficher l'heure avec une date
define('DT_INTERVAL', TRUE); //Permet d'afficher un intervalle de temps plutôt qu'une date

define('CREATE_CACHE', TRUE); //Indique qu'on veut créer le fichier de cache plutôt que le récupérer (utilisé avec cache_categories())

/**
 *	Retourne le titre de l'article à mettre dans une URL, à partir d'un
 *	titre étant déjà passé à la fonction `clean_url`.
 */
function art_title2url($title)
{
	return urlencode(str_replace(' ', '_', $title));
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
 *	@param $nom		Nom de la page, si elle n'existe pas retourne des valeurs par défaut.
 */
function get_page($nom, $namespace = '')
{
    if ($namespace == '')
		$namespace = config_item('namespace_defaut');
	
	$return = array(
		'name' => $nom,
		'title' => art_title($nom),
		'content' => '',
		'pageurl' => pageurl(($namespace != config_item('namespace_defaut') ? $namespace.':' : '').art_title2url($nom)),
		'status' => 'public',
		'lastmodif' => '',
		'lastversion' => 0,
		'categories' => array()
	);
	
	$nom_fichier = PATH_PG.$namespace.'/'.$nom.'.xml';
	if (is_file($nom_fichier))
	{
		$handle = fopen($nom_fichier, 'r');
		$contenu_fichier = fread($handle, filesize($nom_fichier));
		fclose($handle);
		
		$parser = xml_parser_create('UTF-8');
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parse_into_struct($parser, $contenu_fichier, $valeurs, $index_tags);
		xml_parser_free($parser);
		
		$return['name'] = $nom;
		$return['title'] = $valeurs[$index_tags['title'][0]]['value'];
		$return['content'] = trim($valeurs[$index_tags['content'][0]]['value']);
		$return['lastmodif'] = $valeurs[$index_tags['lastmodif'][0]]['value'];
		$return['status'] = $valeurs[$index_tags['status'][0]]['value'];
		$return['lastversion'] = (int)$valeurs[$index_tags['lastversion'][0]]['value'];
		$return['pageurl'] = base_path().pageurl(($namespace != config_item('namespace_defaut') ? $namespace.':' : '').art_title2url($return['title']));
		$return['page_exists'] = TRUE;
		
		if (isset($index_tags['categories']))
			$return['categories'] = explode('|', $valeurs[$index_tags['categories'][0]]['value']);
		
		if (isset($index_tags['redirectto']))
			$return['redirect'] = $valeurs[$index_tags['redirectto'][0]]['value'];
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
	
	$page['content'] = str_replace(array('<![CDATA[', ']]>'), array('&lt;![CDATA[', ']]&gt;'), $page['content']);
	
	$redirect = '';
	if (substr($page['content'], 0, 9) == '#REDIRECT')
		if (preg_match('`#REDIRECT\s*\[\[([^\[]+)]]`i', $page['content'], $r))
			$redirect = "\n\t".'<redirectto>'.art_title2url(clean_title($r[1])).'</redirectto>';
	
	$categories = '';
	//if (preg_match_all('`//Cat[eé]gorie:([^/]+)//`iu', $page['content'], $cats))
	//	$categories = "\n\t".'<categories>'.implode('|', array_map('clean_title', $cats[1])).'</categories>';
	
	$fichier_contenu = '<?xml version="1.0" encoding="UTF-8"?>
<document>
	<title>'.$page['title'].'</title>
	<content><![CDATA['.(!$noparse ? parsewiki($page['content']) : $page['content']).']]></content>
	<lastmodif>'.date('d/m/Y à H:i').'</lastmodif>
	<status>'.$page['status'].'</status>
	<lastversion>'.($createrevision ? $page['lastversion']+1 : $page['lastversion']).'</lastversion>'.$categories.$redirect.'
</document>';
	
	if ($createrevision)
	{
		if ($savelast) save_last_change($page['title'], $ns, $page['lastversion'] + 1);
		write_file(PATH_CNT.'historique/'.$ns.'/'.$page['name'].'/'.($page['lastversion'] + 1).'.txt', $page['content']);
	}
	
	if ($ns == 'Catégorie' || $ns == 'Categorie')
		cache_categories(CREATE_CACHE);
	
	return write_file(PATH_PG.$ns.'/'.$page['name'].'.xml', $fichier_contenu);
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
 *	Créé le fichier de cache contenant la liste des articles.
 */
function generate_cache_list($namespace)
{
	$list_articles = array();
	
	$dossier = opendir(PATH_PG.$namespace);
	while ($page = readdir($dossier))
	{
		if ($page[0] != '.')
		{
			$fichier_page = fopen(PATH_PG.$namespace.'/'.$page, 'r');
			$contenu_page = fread($fichier_page, filesize(PATH_PG.$namespace.'/'.$page));
			fclose($fichier_page);
			
			$pos_title = strpos($contenu_page, '<title>');
			$pos_title_end = strpos($contenu_page, '</title>', $pos_title);
			$titre_page = substr($contenu_page, $pos_title + 7, $pos_title_end - ($pos_title + 7));
			
			if (strpos($contenu_page, '<redirectto>') === FALSE)
				$list_articles[] = $titre_page;
		}
	}
	closedir($dossier);
	
	natcasesort($list_articles);
		
	write_file(PATH_CACHE.$namespace.'_articles.php', 
		'<?php $list_articles = '.var_export($list_articles, TRUE).';');
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
	echo $title.(!empty($title) ? ' - ' : '').config_item('nom_wiki');
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
		mkdir($dossier.'/pages/Catégorie', 0777);
		mkdir($dossier.'/historique/Principal', 0777);
		mkdir($dossier.'/historique/Catégorie', 0777);
		$config = array(
			'page_defaut' => 'Accueil', 'utilisateur' => 'admin', 'nom_wiki' => 'Wikeasy', 'theme' => 'default',
			'motdepasse' => '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'pageurl_type' => 'normal', 
			'salt' => uniqid(mt_rand(), TRUE), 'version' => VERSION, 'nombre_modifs_recentes' => 50, 'proteger_pages' => 0,
			'namespace_defaut' => 'Principal');
		write_file($dossier.'/config.php', '<?php $config = '.var_export($config, TRUE).';');
		create_file(array(
			'name' => 'Accueil', 'title' => 'Accueil', 'content' => "L'installation s'est bien déroulée.\n\nVos ".
			"identifiants de connexion sont __admin__ et __123456__.%%%\nVous pouvez modifier ces informations en ".
			"vous connectant.", 
			'status' => 'public', 'lastversion' => 0));
		redirect();
	}
	else
		exit('Le dossier <strong>content</strong> du wiki n\'est pas accessible en &eacute;criture. Le dossier doit avoir un '.
			 'chmod de 777 pour pouvoir cr&eacute;er le dossier contenant les pages ainsi que le fichier de configuration.');
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

function parsewiki($text)
{
	require_once PATH.'wikirenderer.lib.php';
	
	if (substr($text, 0, 9) == '#REDIRECT')
	{
		preg_match('`#REDIRECT\s*\[\[([^\[]+)]]`i', $text, $r);
		return '<div class="redirect"><p class="descr">Page de redirection</p>'.
			   '<p class="link"><span>→</span> <a href="'.
			   pageurl(art_title2url(clean_title($r[1]))).'">'.
			   htmlspecialchars($r[1]).'</a></p></div>';
	}
	
	$wiki = new WikiRenderer();
	return $wiki->render($text);
}

/**
 *	Retourne l'url de la page en fonction de la configuration.
 */
function pageurl($normal, $rewrite = '')
{
	if (empty($rewrite))
	{
		if (config_item('pageurl_type') == 'rewrite') return $normal;
		return 'index.php?page='.$normal;
	}
	
	if (config_item('pageurl_type') == 'rewrite') return $rewrite;
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
		$recentchanges = unserialize(file_get_contents(PATH_CACHE.'modifications_recentes'));
	
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
 *	@param	(string) $page		Nom de la page.
 *	@param	(bool)	 $dirtoo	S'il faut aussi supprimer le dossier.
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
 *	@param	(int)		$nbr	Nombre à tester
 *	@param	(string)	$sing	Singulier du mot ou de la phrase
 *	@param	(string)	$plu	Pluriel à retourner (facultatif)
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
	
	if ($dir = opendir(PATH_CNT.'suppressions'))
	{
		while (($art = readdir($dir)) !== FALSE)
			if ($art[0] != '.')
				$deleted[] = $art;
		closedir($dir);
	}
	
	write_file(PATH_CACHE.'liste_supprimes', serialize(array_flip($deleted)));
}

/**
 *	Liste des articles supprimés.
 */
function deleted_articles()
{
	if (!is_file(PATH_CACHE.'liste_supprimes'))
		generate_deleted_articles_cache();
	
	return unserialize(file_get_contents(PATH_CACHE.'liste_supprimes'));
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
	
	if (empty($_config))
	{
		if (is_file(PATH_CNT.'config.php'))
		{
			require PATH_CNT.'config.php';
			
			$_config = $config;
		}
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
function cache_categories($create = FALSE)
{
    $file = PATH_CACHE.'categories';
    
    if ($create == CREATE_CACHE)
    {
        $cats = array();
        $dir = dir(PATH_CACHE.'Catégorie');
        while (($cat = $dir->read()) !== FALSE)
        {
            if ($cat[0] != '.')
            {
                $cat = substr($cat, 0, strpos($cat, '.'));
                $cats[$cat] = art_title($cat);
            }
        }
        $dir->close();
        write_file($file, serialize($cats));
    }
    else
    {
        if (!is_file($file))
            cache_categories(CREATE_CACHE);
        
        return unserialize(file_get_contents($file));
    }
}

/* End of file functions.php */