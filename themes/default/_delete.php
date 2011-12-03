	<h1>Supprimer une page</h1>
			<form method="post" action="<?php echo $page['pageurl'].$and.'a=supprimer'; ?>" accept-charset="utf-8"><?php
				if (isset($erreur)) echo '<p class="erreur">'.$erreur.'</p>'; ?>

				<p>
					Êtes-vous sûr de vouloir supprimer la page « <strong><?php echo ns_name($namespace).$page['title']; ?></strong> » ?
					<input type="hidden" name="_delnonce" value="<?php echo create_nonce('delete-page'); ?>" />
				</p>
				<input type="submit" value="Supprimer" name="suppr_ok" class="submit" /> 
				<a href="<?php echo $page['pageurl'].(isset($page['redirect']) ? $and.'redirect=no' : ''); ?>">Retour</a>
			</form>