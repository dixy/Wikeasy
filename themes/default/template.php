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
				
				<?php if ($_SESSION['wik_connect'] && ($mode == 'lire' || $mode == 'modifier' || $mode == 'historique') && isset($page['page_exists'])) : ?><li class="right"><a href="<?php echo $page['pageurl'].$and.'a=supprimer'; ?>" accesskey="s">Supprimer</a></li>
				<li class="right"><a href="<?php echo $page['pageurl'].$and.'a=renommer'; ?>" accesskey="r">Renommer</a></li><?php endif; ?>
				<?php if (($mode == 'modifier' || $mode == 'lire' || $mode == 'historique') && isset($page['page_exists'])) : ?><li class="right"><a href="<?php echo $page['pageurl'].$and.'a=historique'; ?>" accesskey="h">Historique</a></li><?php endif; ?>
				<?php if (($mode == 'lire' || $mode == 'historique') && (check_access($page) || isset($page['page_exists']))) : ?><li class="right"><a href="<?php echo $page['pageurl'].$and.'a=modifier'; ?>" accesskey="m"><?php if (check_access($page)) : echo (isset($page['page_exists']) ? 'Modifier cet article' : 'Créer cet article'); else : echo 'Texte source'; endif; ?></a></li><?php endif; ?>

			</ul>
		</div>
		
		<div id="content">
		<?php if ($mode == 'lire') : ?>
			<h1><?php echo ($namespace != config_item('namespace_defaut') ? $namespace.':' : '').$page['title']; ?></h1>
			<?php if (isset($page['page_exists'])) : ?>
				<?php echo $page['content']; ?>
				
				<?php if (!empty($page['categories'])) : ?>
					<div class="categories">
						<?php echo plural(count($page['categories']), 'Catégorie'); ?> : 
						<?php echo implode(' | ', array_map(function ($c) { return '<a href="'.base_path().pageurl('Catégorie:'.art_title2url($c)).'">'.art_title($c).'</a>'; }, $page['categories'])); ?>

					</div>
				<?php endif; ?>
			<?php else : ?>
				<?php if ($was_deleted) : ?>
					<p class="avertissement">
						Cet article a été supprimé.
					</p>
				<?php endif; ?>
				<p>
					Cet article n'existe pas.
					<?php if (check_access($page)) : ?>Vous pouvez <a href="<?php echo $page['pageurl'].$and.'a=modifier'; ?>">Créer cet article</a>.<?php endif; ?>

				</p>
			<?php endif; ?>
		<?php elseif ($mode == 'modifier') : ?>
			<?php if (check_access($page)) : ?>
			<h1><?php echo (isset($page['page_exists']) ? 'Modification' : 'Création'); ?> de <?php echo $page['title']; ?></h1>
			
			<form method="post" action="<?php echo $page['pageurl'].$and.'a=modifier'; ?>">
				<?php if (isset($erreur)) echo '<p class="erreur">'.$erreur.'</p>'; ?>

				<div class="formulaire_modif">
					<textarea name="contenu_page" id="pg_cnt" cols="50" rows="25" tabindex="1"><?php echo htmlspecialchars($page_to_edit); ?></textarea>
					
					<div class="choix_categories">
						Catégories : <span id="cats"><?php
						if (empty($page['categories'])) :
							echo '(aucune)';
						else :
							echo implode(', ', array_map(function ($c) { return '<input type="hidden" name="categories_page[]" value="'.$c.'" />'.$c; }, $page['categories']));
						endif;
						?></span> 
						<select name="ajout_cat_nom" id="ajout_cat_nom" tabindex="3">
							<option value="0"></option>
							<?php foreach ($categories as $c_nom => $c_titre) : ?><option value="<?php echo $c_nom; ?>"><?php echo $c_titre; ?></option>
							<?php endforeach; ?>

						</select>
						<input type="submit" value="Ajouter" name="ajout_categorie" class="submit" accesskey="c" />
					</div>
					
					<?php if ($_SESSION['wik_connect']) : ?>
						<label for="change_status">
							<input type="checkbox" id="change_status" name="change_status" tabindex="5" value="1" <?php if ($page['status'] == 'private') echo 'checked="checked" '; ?>/> 
							Empêcher la modification de cette page par les visiteurs
						</label>
					<?php endif; ?>

					<p class="center">
						<input type="submit" name="enreg_page" value="Enregistrer" class="submit" accesskey="e" tabindex="10" /> 
						<input type="submit" name="previ_page" value="Prévisualiser" class="submit" accesskey="p" tabindex="12" /> 
						<a href="<?php echo $page['pageurl'].(isset($page['redirect']) ? $and.'redirect=no' : ''); ?>" tabindex="14">Annuler</a>
					</p>
				</div>
				
				<?php if (isset($apercu_page)) : ?>
					<div class="previsualisation">
						<h6>Prévisualisation</h6>
						<?php echo $apercu_page; ?>
					</div>
				<?php endif; ?>
			</form>
			<script type="text/javascript">document.getElementById('pg_cnt').focus();</script>
			<?php else : ?>
			<h1>Voir le texte source de <?php echo $page['title']; ?></h1>
			<p>Vous n’êtes pas autorisé(e) à modifier cette page, mais vous pouvez voir et copier le contenu de la page :</p>
			<p><textarea cols="50" rows="25" style="width:100%;" tabindex="1"><?php echo htmlspecialchars($page_to_edit); ?></textarea></p>
			<p>Revenir à la page <a href="<?php echo $page['pageurl']; ?>"><?php echo $page['title']; ?></a></p>
			<?php endif; ?>
		<?php elseif ($mode == 'liste') : ?>
			<h1>Liste des articles</h1>
			<?php if (empty($list_articles)) : ?>
				<p>Aucun article trouvé.</p>
			<?php else : ?>
				<p><?php echo count($list_articles).' '.plural(count($list_articles), 'article trouvé', 'articles trouvés').'.'; ?></p>
				
				<?php
				$derniere_lettre = '';
				foreach ($list_articles as $title)
				{
					$lettre = strtoupper(substr($title, 0, 1));
					if ($lettre != $derniere_lettre)
					{
						if (!empty($derniere_lettre)) echo '</ul>';
						echo '<h3>'.$lettre.'</h3><ul>';
						$derniere_lettre = $lettre;
					}
					echo '<li><a href="'.pageurl(art_title2url($title)).'">'.$title.'</a></li>';
				}
				echo '</ul>';
			endif; ?>
		<?php elseif ($mode == 'connexion') : ?>
			<h1>Connexion</h1>
			<form method="post" action="<?php echo base_path(); ?>index.php?a=connexion">
				<?php if (isset($erreur)) echo '<p class="erreur">'.$erreur.'</p>'; ?>

				<div class="bloc_connexion">
					<p><label for="util">Nom d'utilisateur : </label>
					<input type="text" id="util" name="util" size="20" /></p>
					
					<p><label for="mdp">Mot de passe : </label>
					<input type="password" id="mdp" name="mdp" size="20" /></p>
					
					<input type="submit" value="Connexion !" class="submit" />
				</div>
			</form>
		<?php elseif ($mode == 'historique') : ?>
			<?php if (isset($see_content)) : ?>
				<h1>Version de « <?php echo $page['title']; ?> » du <?php echo $versions[(int)$_GET['s']]; ?></h1>
				<p>
					<a href="<?php echo $page['pageurl']; ?>">Retour à l'article</a> - 
					<a href="<?php echo $page['pageurl'].$and.'a=historique'; ?>">Retour à l'historique</a>
					<?php if ($page['lastversion'] != $_GET['s'] && check_access($page)) : ?>
					 - <a href="<?php echo $page['pageurl'].$and.'a=modifier&amp;r='.(int)$_GET['s']; ?>">Restaurer</a>
					<?php endif; ?>
				</p>
				<?php echo $see_content; ?>
			<?php elseif (isset($diff)) : ?>
				<h1>Différences entre les versions de « <?php echo $page['title']; ?> »</h1>
				<p>
					<a href="<?php echo $page['pageurl']; ?>">Retour à l'article</a> - 
					<a href="<?php echo $page['pageurl'].$and.'a=historique'; ?>">Retour à l'historique</a>
				</p>
				<table class="diff">
					<thead>
						<tr>
							<th class="center" colspan="2"><?php echo $diff['old_date']; ?></th>
							<th class="center" colspan="2"><?php echo $diff['new_date']; ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if (!empty($diff['rows'])) : ?>
						<?php foreach ($diff['rows'] as $k => $row) : ?>
							<tr>
							<?php foreach ($row as $cell) : ?>
								<td<?php if (isset($cell['colspan'])) echo ' colspan="'.$cell['colspan'].'"';
								if (isset($cell['class'])) echo ' class="'.$cell['class'].'"'; ?>>
								<?php echo $cell['data']; ?>
								</td>
							<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="4">Pas de différence</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			<?php else : $nbr = count($versions); ?>
				<h1>Historique des versions de « <?php echo $page['title']; ?> »</h1>
				
				<p>
					<a href="<?php echo $page['pageurl']; ?>">Retour à l'article</a>
					<?php if (check_access($page)) : ?>
					 - <a href="<?php echo $page['pageurl'].$and.'a=modifier'; ?>">Aller au formulaire de modification</a>
					<?php endif; ?>
				</p>
				
				<?php if (isset($erreur)) echo '<p class="erreur">'.$erreur.'</p>'; ?>
				<form method="post" action="<?php echo $page['pageurl'].$and.'a=historique'; ?>">
					<?php if ($nbr > 1) : ?><p><input type="submit" value="Comparer les versions sélectionnées" class="submit" /></p><?php endif; ?>
					<table border="1"><thead><tr>
						<th>Numéro</th>
						<th>Date</th>
						<?php if (check_access($page) && $nbr > 1) : ?><th>&nbsp;</th><?php endif; ?>
					</tr></thead><tbody>
					<?php foreach ($versions as $vid => $vdate) : ?>
						<tr>
							<td class="center"><?php echo $vid; ?></td>
							<td>
								<?php if ($nbr > 1) : ?>
									<input type="radio" name="oldid" value="<?php echo $vid; ?>" <?php if ($vid == $nbr-1) echo 'checked="checked" '; ?>/> 
									<input type="radio" name="recentid" value="<?php echo $vid; ?>" <?php if ($vid == $nbr) echo 'checked="checked" '; ?>/> &nbsp;
								<?php endif; ?>
								
								<a href="<?php echo $page['pageurl'].$and.'a=historique&amp;s='.$vid; ?>"><?php echo $vdate; ?></a> <?php if ($vid == $page['lastversion']) echo ' [Version actuelle]'; ?></td>
							<?php if (check_access($page) && $nbr > 1) : ?><td>
								<?php if ($vid != $page['lastversion']) : ?>
									<a href="<?php echo $page['pageurl'].$and.'a=modifier&amp;r='.$vid; ?>">Restaurer</a>
								<?php endif; ?>
							</td><?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
					<?php if ($nbr > 1) : ?><p><input type="submit" value="Comparer les versions sélectionnées" class="submit" /></p><?php endif; ?>
				</form>
			<?php endif; ?>
		<?php elseif ($mode == 'modifications-recentes') : ?>
			<h1>Modifications récentes</h1>
			<?php if (empty($recentchanges)) : ?>
				<p>Aucune modification pour l'instant.</p>
			<?php else :
				$dernier_jour = '';
				$ns_default = config_item('namespace_defaut');
				foreach ($recentchanges as $change)
				{
					if ($change['date'] != $dernier_jour)
					{
						if (!empty($dernier_jour)) echo '</ul>';
						echo '<h3>'.$change['date'].'</h3><ul>';
						$dernier_jour = $change['date'];
					}
					
					$ns = $ns_default != $change['namespace'] ? $change['namespace'].':' : '';
					$diff = 'diff'; $plus = ''; 
					$pageurl = pageurl($ns.art_title2url($change['pagetitle']));
					$hist = '<a href="'.$pageurl.$and.'a=historique">hist</a>';
					
					if (isset($change['version']))
					{
						if ($change['version'] > 1)
							$diff = '<a href="'.$pageurl.$and.'a=historique&amp;oldid='.($change['version']-1).
										  '&amp;recentid='.$change['version'].'">diff</a>';
						
						if ($change['version'] == 1)
							$plus = ' &nbsp;<em>(Nouvelle page)</em>';
					}
					
					if (isset($change['oldname'])) 
						$what = '<a href="'.pageurl($ns.art_title2url($change['oldname'])).$and.'redirect=no">'.$ns.$change['oldname'].'</a> '.
								'renommé en <a href="'.$pageurl.'">'.$ns.$change['pagetitle'].'</a>';
					elseif (isset($change['undelete']))
						$what = 'Restauration de <a href="'.$pageurl.'">'.$ns.$change['pagetitle'].'</a>';
					elseif (isset($change['delete']))
						{ $what = 'Suppression de <a href="'.$pageurl.'">'.$ns.$change['pagetitle'].'</a>'; $hist = 'hist'; }
					else
						$what = '<a href="'.$pageurl.'">'.$ns.$change['pagetitle'].'</a>';
					
					echo '<li>('.$diff.') ('.$hist.') &nbsp;'.$what.' - '.$change['hour'].$plus.'</li>'."\n";
				}
				echo '</ul>';
			endif;
		elseif ($mode == 'redirections') : ?>
			<h1>Liste des redirections</h1>
			<?php if (empty($redirects_list)) : ?>
				<p>Aucune redirection trouvée.</p>
			<?php else : ?>
				<p><?php echo count($redirects_list).' '.plural(count($redirects_list), 'redirection trouvée', 'redirections trouvées').'.'; ?></p>
				
				<ul>
					<?php foreach ($redirects_list as $name => $title) : ?>
						<li><a href="<?php echo pageurl(art_title2url($title)).$and.'redirect=no'; ?>"><?php echo $title; ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif;
		elseif ($mode == 'renommer') : require PATH.theme_url(RETURN_VAL).'_rename.php';
		elseif ($mode == 'supprimer') : require PATH.theme_url(RETURN_VAL).'_delete.php';
		elseif ($mode == 'parametres') : require PATH.theme_url(RETURN_VAL).'_parameters.php';
		elseif ($mode == 'suppressions') : require PATH.theme_url(RETURN_VAL).'_deletions.php'; endif; ?>

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

			<?php if ($mode == 'lire' && !empty($page['lastmodif'])) : ?>

			<p class="dernmodif">Dernière modification de cette page le <?php echo $page['lastmodif']; ?></p><?php endif; ?>

		</div>
	</body>
</html>