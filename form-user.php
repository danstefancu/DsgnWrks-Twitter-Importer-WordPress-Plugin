<form class="twitter-importer" method="post" action="options.php">
	<?php settings_fields( $this->name( 'users' ) ); ?>

	<p>Enter another Twitter username to import their tweets</p>

	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label><strong>Twitter Username:</strong></label></th>
			<td><strong class="atsymbol">@</strong><input type="text" name="<?php echo $this->name( 'users' ); ?>" value="" /></td>
		</tr>
	</table>

	<p class="submit">
		<input type="submit" name="save" class="button-primary" value="<?php _e( 'Save' ) ?>" />
	</p>
</form>