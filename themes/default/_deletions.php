	<?php if (isset($undelete)) : ?><h1>Restaurer une page supprimée</h1>
			<?php if (isset($erreur)) : ?><div class="bloc-erreur"><?php echo $erreur; ?></div><?php endif; ?>

			
			<p>Vous êtes entrain de restaurer la page &laquo; <?php echo $undelete['title']; ?> &raquo;</p>
			<?php if ($check_exists) : ?><p class="erreur">
				Attention ! Un article du même nom a été créé après la suppression de celui-ci.
			</p><?php endif; ?>

			
			<fieldset id="undelete-text">
				<legend>Version de <?php echo $undelete['title']; ?> du <?php echo format_date($undelete['deletetime'], DT_HOUR); ?></legend>
				<?php echo parsewiki($undelete['content']); ?>

			</fieldset>
			
			<form method="post" action="<?php echo base_path(); ?>index.php?a=suppressions&amp;r=<?php echo $_GET['r']; ?>">
				<input type="hidden" name="_undelnonce" value="<?php echo create_nonce('undelete-page'); ?>" />
				<?php if ($check_exists) : ?>
					<p>
						Un article avec le même nom existe déjà. Choisissez quelle action effectuer :<br />
						<input type="radio" name="undel_act" value="new" checked="checked" /> Donner un nouveau nom à l'article :
						<input type="text" name="new_title" value="<?php echo $_GET['r']; ?>" /><br />
						<input type="radio" name="undel_act" value="delete" /> Supprimer l'article en ligne et remplacer par celui-ci
					</p>
				<?php endif; ?>

				<input type="submit" value="Restaurer" name="undelete_ok" class="submit" />
				<a href="<?php echo base_path(); ?>index.php?a=suppressions">Annuler</a>
			</form>
		<?php else : ?><h1>Liste des pages supprimées</h1><?php
			 if (isset($erreur)) echo '<div class="bloc-erreur">'.$erreur.'</div>'; ?>

			<?php if (empty($pages_deleted)) : ?><p>Aucune page supprimée trouvée.</p><?php else : ?>
	
			<p><?php echo count($pages_deleted).' '.plural(count($pages_deleted), 'page supprimée trouvée', 'pages supprimées trouvées').'.'; ?></p>
			
			<ul>
				<?php foreach ($pages_deleted as $name => $article) : ?>

				<li><a href="<?php echo base_path().'index.php?a=suppressions&amp;r='.$name; ?>"><?php echo $article['title']; 
				?></a> supprimée le <?php echo format_date($article['deletetime'], DT_HOUR); 
				if ($article['isredirect']) : ?> <em>(redirection)</em><?php endif; ?></li><?php endforeach; ?>

			</ul>
			<?php endif;
		endif; ?>