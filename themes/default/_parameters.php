			<h1>Paramètres</h1>
			<form method="post" action="<?php echo base_path(); ?>index.php?a=parametres"><?php
				if (isset($erreur)) echo '<p class="erreur">'.$erreur.'</p>'; ?>

				<fieldset>
					<legend>Configuration de base</legend>
					
					<p><label for="nom_wiki">Nom du wiki :</label></p>
					<input type="text" name="config[nom_wiki]" id="nom_wiki" value="<?php echo config_item('nom_wiki'); ?>" size="40" />
					
					<p><label for="theme">Thème :</label></p>
					<select name="config[theme]" id="theme">
					<?php if (!empty($liste_themes)) : foreach ($liste_themes as $tnom) : ?>
						<option value="<?php echo $tnom; ?>"<?php if ($tnom == config_item('theme')) echo ' selected="selected"'; ?>><?php echo $tnom; ?></option>
					<?php endforeach; endif; ?>
					</select>
					
					<p><label for="nombre_modifs_recentes">Nombre de modifications à conserver dans les <a href="<?php echo base_path(); ?>index.php?a=modifications-recentes">modifications récentes</a> :</label></p>
					<input type="text" name="config[nombre_modifs_recentes]" id="nombre_modifs_recentes" value="<?php echo config_item('nombre_modifs_recentes'); ?>" size="10" />
					
					<p><label for="proteger_pages"><input type="checkbox" name="config[proteger_pages]" id="proteger_pages" <?php if (config_item('proteger_pages')) echo 'checked="checked" '; ?>/> 
					Protéger toutes les pages (seuls les utilisateurs connectés pourront les modifier)</label></p>
				</fieldset>
				<fieldset>
					<legend>Compte de l'administrateur</legend>
					
					<p><label for="utilisateur">Nom d'utilisateur :</label></p>
					<input type="text" name="config[utilisateur]" id="utilisateur" value="<?php echo config_item('utilisateur'); ?>" size="40" />
					
					<p><label for="motdepasse">Mot de passe (laissez vide pour ne pas changer) :</label></p>
					<input type="text" name="config[motdepasse]" id="motdepasse" size="40" /> (minimum 6 caractères)
				</fieldset>
				<fieldset>
					<legend>Adresses des pages</legend>
					<?php if ($rewrite_status) : ?>
						<p><label><input type="radio" name="config[pageurl_type]" value="rewrite" <?php if (config_item('pageurl_type') == 'rewrite') echo 'checked="checked" '; ?>/> Adresses simplifiées c'est-à-dire sans <code>index.php?page=</code> (nécessite le mod_rewrite d'Apache).</label></p>
						<p><label><input type="radio" name="config[pageurl_type]" value="normal" <?php if (config_item('pageurl_type') == 'normal') echo 'checked="checked" '; ?>/> Adresses normales (fonctionne partout)</label></p>
					<?php else : ?>
						<p>L'URL rewriting n'est pas activé chez votre hébergeur, il est donc impossible de l'utiliser pour votre wiki.</p>
					<?php endif; ?>
				</fieldset>
				<p class="center">
					<input type="hidden" name="_cfgnonce" value="<?php echo create_nonce('modify-configuration'); ?>" />
					<input type="submit" value="Enregistrer" class="submit" />
				</p>
			</form>