	<h1>Renommer <?php echo ($namespace != config_item('namespace_defaut') ? $namespace.':' : '').$page['title']; ?></h1>
			<form method="post" action="<?php echo $page['pageurl'].$and.'a=renommer'; ?>"><?php
				if (isset($erreur)) echo '<p class="erreur">'.$erreur.'</p>'; ?>

				<p>Renommer la page : <a href="<?php echo $page['pageurl']; ?>"><?php echo ($namespace != config_item('namespace_defaut') ? $namespace.':' : '').$page['title']; ?></a></p>
				<p>Nouveau titre : <input type="text" size="30" value="<?php echo $page['title']; ?>" name="page_newtitle" /></p>
				<input type="hidden" name="_rennonce" value="<?php echo create_nonce('rename-page'); ?>" />
				<input type="submit" value="Renommer la page" class="submit" /> 
				<a href="<?php echo $page['pageurl'].(isset($page['redirect']) ? $and.'redirect=no' : ''); ?>">Annuler</a>
			</form>