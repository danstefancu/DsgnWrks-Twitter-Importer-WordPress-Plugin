<?php
/**
 * @var $this DsgnWrksTwitter
 */

add_thickbox();

$opts = $this->get_option( 'options' );

$active_from_cookie = isset( $_COOKIE[$this->name('active_username')] ) ? $_COOKIE[$this->name('active_username')] : '';
?>

<div class="wrap">
	<div id="icon-tools" class="icon32"><br></div>
	<h2>DsgnWrks Twitter Importer Options</h2>
	<div id="screen-meta" style="display: block; ">

	<div class="clear"></div>

		<div id="contextual-help-wrap" class="hidden" style="display: block; ">
			<div id="contextual-help-back"></div>
			<div id="contextual-help-columns">
				<div class="contextual-help-tabs">
					<?php if ( empty( $opts ) ) { ?>
						<h2>Get Started</h2>
					<?php } else { ?>
						<h2>Users</h2>
					<?php } ?>

					<ul>

						<?php

						foreach ( $opts as $username => $user_options ) {

							$active = $active_from_cookie == $username ? 'active' : '';
							?>
							<li class="tab-twitter-user <?php echo $active; ?>" id="tab-twitter-user-<?php echo $username; ?>">
								<a href="#twitter-user-<?php echo $username; ?>"><?php echo $username; ?></a>
							</li>
						<?php } ?>

						<?php if ( empty( $opts ) ) { ?>
							<li class="tab-twitter-user active" id="tab-twitter-user-new">
								<a href="#twitter-user-new">Create user</a>
							</li>
						<?php } else { ?>
							<li id="tab-add-another-user">
								<a href="#add-another-user">Add Another User</a>
							</li>
						<?php } ?>
					</ul>
				</div>

				<div class="contextual-help-tabs-wrap">

				<?php
				if ( !empty( $opts ) && is_array( $opts ) ) {
					?>
					<form class="twitter-importer" method="post" action="<?php echo admin_url( 'options.php' ); ?>">
						<?php settings_fields( $this->name( 'options' ) );

					foreach ( $opts as $username => $user_options ) {
						$user_options = wp_parse_args( $user_options, $this->defaults() );
						$active = $active_from_cookie == $username ? 'active' : '';
						?>
						<div id="twitter-user-<?php echo $username; ?>" class="help-tab-content <?php echo $active; ?>">


							<table class="form-table">

								<tr valign="top" class="info">
								<th colspan="2">
									<p>Ready to import from Twitter &mdash; <span>
											<?php
											$delete_url = add_query_arg( 'action', 'delete', admin_url( 'admin.php' ) );
											$delete_url = add_query_arg( 'twitter_username', $username, $delete_url );
											$delete_url = add_query_arg( '_wpnonce', wp_create_nonce( $this->name( 'options' ) ), $delete_url );
											?>
											<a class="delete-button" href="<?php echo $delete_url; ?>"><?php _e( 'Delete?' ); ?></a>
											</span>
									</p>
									<p>Please select the import filter options below. If none of the options are selected, all tweets for <strong><?php echo $username; ?></strong> will be imported. <em>(This could take a long time if you have a lot of tweets)</em></p>
								</th>
								</tr>

								<tr valign="top">
									<th scope="row"><strong>Filter import by hashtag:</strong><br/>Will only import tweets with these hashtags.<br/>Please separate tags (without the <b>#</b> symbol) with commas.</th>
									<td><input type="text" placeholder="e.g. keeper, fortheblog" <?php $this->option_name( $username, 'tag-filter' ); ?> value="<?php echo $user_options['tag-filter']; ?>" /></td>
								</tr>

								<tr valign="top">
								<th scope="row"><strong>Import from this date:</strong><br/>Select a date to begin importing your tweets.</th>

								<td class="curtime">
									<?php
									global $wp_locale;

									$date_filter = 0;
									if ( !empty( $user_options['mm'] ) || !empty( $user_options['dd'] ) || !empty( $user_options['yy'] ) ) {
										if ( $user_options['date-filter'] ) {
											$date = '<strong>'. $wp_locale->get_month( $user_options['mm'] ) .' '. $user_options['dd'] .', '. $user_options['yy'] .'</strong>';
												$date_filter = strtotime( $user_options['mm'] .'/'. $user_options['dd'] .'/'. $user_options['yy'] );
										} else {
											$date = '<span style="color: #E0522E;">Please select full date</span>';
										}
									} else {
										$date = 'No date selected';
									} ?>
									<div class="timestamp-wrap">
										<p style="padding-bottom: 2px; margin-bottom: 2px;" id="timestamp"><?php echo $date; ?></p>

										<input type="hidden" <?php $this->option_name( $username, 'date-filter' ); ?> value="<?php echo $user_options['date-filter']; ?>" />

										<select id="twitter-mm" <?php $this->option_name( $username, 'mm' ); ?>>
											<option value="0">Month</option>
											<?php for ( $i = 1; $i <= 12; $i++ ) {
											$value = zeroise($i, 2);
											?>
											<option value="<?php echo $value; ?>" <?php selected( $i, $user_options['mm'] ); ?>>
												<?php echo $value . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ); ?>
											</option>

											<?php } ?>
										</select>

										<select id="twitter-dd" <?php $this->option_name( $username, 'dd' ); ?>>
											<option value="0">Day</option>
											<?php for ( $i = 1; $i <= 31; $i++ ) {
												$value = zeroise($i, 2);
												?>
												<option value="<?php echo $value; ?>" <?php selected( $i, $user_options['dd'] ); ?>>
													<?php echo $value; ?>
												</option>

											<?php } ?>
										</select>

										<select style="width: 5em;" id="twitter-mm" <?php $this->option_name( $username, 'yy' ); ?>>
											<option value="0">Year</option>
											<?php for ( $i = date( 'Y' ); $i >= 2010; $i-- ) {
												$value = zeroise($i, 4);
												?>
												<option value="<?php echo $value; ?>" <?php selected( $i, $user_options['yy'] ); ?>>
													<?php echo $value; ?>
												</option>

											<?php } ?>
										</select>

										<?php if ( $user_options['date-filter'] ) { ?>
											<p><label><input type="checkbox" <?php $this->option_name( $username, 'remove-date-filter'); ?> value="1" /> <em> Remove filter</em></label></p>
										<?php } ?>

									</div>
								</td>
								</tr>

								<tr valign="top" class="info">
								<th colspan="2">
									<p>Please select the post options for the imported tweets below.</em></p>
								</th>
								</tr>


								<tr valign="top">
								<th scope="row"><strong>Import to Post-Type:</strong></th>
								<td>
									<select class="twitter-post-type" id="twitter-post-type-<?php echo $username; ?>" <?php $this->option_name( $username, 'post-type'); ?>>
										<?php
										$post_types = get_post_types( array( 'public' => true ) );
										foreach ($post_types  as $post_type ) {
											?>
											<option value="<?php echo $post_type; ?>" <?php selected( $user_options['post-type'] , $post_type ); ?>><?php echo $post_type; ?></option>
											<?php
										}
										?>
									</select>
								</td>
								</tr>


								<tr valign="top">
								<th scope="row"><strong>Imported posts status:</strong></th>
								<td>
									<select id="twitter-draft" <?php $this->option_name( $username, 'draft'); ?>>
										<option value="draft" <?php selected( $user_options['draft'], 'draft' ); ?>>Draft</option>
										<option value="publish" <?php selected( $user_options['draft'], 'publish' ); ?>>Published</option>
										<option value="pending" <?php selected( $user_options['draft'], 'pending' ); ?>>Pending</option>
										<option value="private" <?php selected( $user_options['draft'], 'private' ); ?>>Private</option>
									</select>

								</td>
								</tr>

								<tr valign="top">
									<th scope="row"><strong>Assign posts to an existing user:</strong></th>
									<td><?php wp_dropdown_users( array( 'name' => sprintf( '%s[%s][%s]', $this->name( 'options' ), $username, 'author' ), 'selected' => $user_options['author'] ) ); ?></td>
								</tr>

								<tr valign="top">
									<th scope="row"><strong>Exclude replies</strong></th>
									<td>
										<input type="checkbox" <?php $this->option_name( $username, 'no-replies'); ?> value="1" <?php echo checked( 1, $user_options['no-replies'] ); ?> />
									</td>
								</tr>

								<tr valign="top">
									<th scope="row"><strong>Exclude retweets</strong></th>
									<td>
										<input type="checkbox" <?php $this->option_name( $username, 'no-retweets'); ?> value="1" <?php echo checked( 1, $user_options['no-retweets'] ); ?> />
									</td>
								</tr>

								<?php
								if ( current_theme_supports( 'post-formats' ) && post_type_supports( 'post', 'post-formats' ) ) {
									$post_formats = get_theme_support( 'post-formats' );

									if ( is_array( $post_formats[0] ) ) {
										// Add in the current one if it isn't there yet, in case the current theme doesn't support it
										if ( $user_options['post_format'] && !in_array( $user_options['post_format'], $post_formats[0] ) )
											$post_formats[0][] = $user_options['post_format'];
										?>
										<tr valign="top" class="taxonomies-add">
										<th scope="row"><strong>Select Imported Posts Format:</strong></th>
										<td>

											<select id="dsgnwrks_tweet_options[<?php echo $username; ?>][post_format]" <?php $this->option_name( $username, 'post-format'); ?>>
												<option value="0" <?php selected( $user_options['post_format'], '' ); ?>>Standard</option>
												<?php foreach ( $post_formats[0] as $format ) : ?>
												<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $user_options['post_format'], $format ); ?>><?php echo esc_html( get_post_format_string( $format ) ); ?></option>

												<?php endforeach; ?>

											</select>

										</td>
										</tr>
										<?php
									}
								}

								$taxs = get_taxonomies( array( 'public' => true ), 'objects' );
								$taxes = array();

								?>
								<tr valign="top">
								<th scope="row"><strong><?php _e( 'Save Tweet hashtags as one of your taxonomies (tags, categories, etc):', 'dsgnwrks' ); ?></strong></th>
								<td>
									<select <?php $this->option_name( $username, 'hashtags_as_tax' ); ?>>
										<option class="empty" value="" <?php selected( $user_options['hashtags_as_tax'], '' ); ?>>Select</option>
										<?php
										foreach ( $taxs as $key => $tax ) {

											$pt_taxes = get_object_taxonomies( $user_options['post-type'] );
											$disabled = !in_array( $tax->name, $pt_taxes ); ?>
											<option class="taxonomy-<?php echo $tax->name; ?>" value="<?php echo esc_attr( $tax->name ); ?>"  <?php selected( $user_options['hashtags_as_tax'], $tax->name ); disabled( $disabled ); ?>> <?php echo esc_html( $tax->label ); ?></option>

										<?php } ?>
									</select>

								</td>
								</tr>
								<?php foreach ( $taxs as $key => $tax ) {
									$user_options[$tax->name] = !empty( $user_options[$tax->name] ) ? esc_attr( $user_options[$tax->name] ) : '';
									?>
									<tr valign="top" class="taxonomies-add taxonomy-<?php echo $tax->name; ?>">
									<th scope="row">
										<strong> <?php echo $tax->label; ?> to apply to imported posts.</strong><br/>Please separate with commas.
									</th>
									<td>
										<input type="text" placeholder="e.g. Twitter, Life, dog, etc" <?php $this->option_name( $username, $tax->name ); ?> value="<?php echo $user_options[$tax->name]; ?>" />
									</td>
									</tr>
								<?php } ?>

							</table>

							<p class="submit">
								<?php
								$import_url = add_query_arg( 'action', 'import', admin_url( 'admin.php' ) );
								$import_url = add_query_arg( 'twitter_username', $username, $import_url );
								$import_url = add_query_arg( '_wpnonce', wp_create_nonce( $this->name( 'options' ) ), $import_url );
								?>
								<input type="submit" id="save-<?php echo sanitize_title( $username ); ?>" name="save" class="button-primary" value="<?php _e( 'Save' ) ?>" />
								<a class="button-secondary import-button" href="<?php echo $import_url; ?>"><?php _e( 'Import' ); ?></a>
							</p>
						</div>
						<?php
					}
					?>
					</form>

					<?php
				} else {

					include( 'form-user.php' );
				}

				if ( !$nogo ) { ?>
					<div id="add-another-user" class="help-tab-content <?php echo ( $nofeed == true ) ? ' active' : ''; ?>">
						<?php include( 'form-user.php' ); ?>
					</div>
				<?php } ?>
				</div>

				<div class="contextual-help-sidebar">
				</div>

			</div>
		</div>
	</div>
</div>