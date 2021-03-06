<?php
/**
 * Позволяет администраторам удалять старые темы с сайта.
 *
 * @copyright Copyright (C) 2008 PunBB, partially based on code copyright (C) 2008 FluxBB.org
 * @modified Copyright (C) 2014-2017 Flazy.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package Flazy
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/functions/admin.php';

($hook = get_hook('apr_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_prune.php';


if (isset($_GET['action']) || isset($_POST['prune']) || isset($_POST['prune_comply']))
{
	if (isset($_POST['prune_comply']))
	{
		$prune_from = $_POST['prune_from'];
		$prune_sticky = isset($_POST['prune_sticky']) ? '1' : '0';
		$prune_days = intval($_POST['prune_days']);
		$prune_date = ($prune_days) ? time() - ($prune_days*86400) : -1;

		($hook = get_hook('apr_prune_comply_form_submitted')) ? eval($hook) : null;

		@set_time_limit(0);

		if (!defined('FORUM_FUNCTIONS_SYNS'))
			require FORUM_ROOT.'include/functions/synchronize.php';

		if ($prune_from == 'all')
		{
			$query = array(
				'SELECT'	=> 'f.id',
				'FROM'		=> 'forums AS f'
			);

			($hook = get_hook('apr_prune_comply_qr_get_all_forums')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			$num_forums = $forum_db->num_rows($result);

			for ($i = 0; $i < $num_forums; ++$i)
			{
				$fid = $forum_db->result($result, $i);

				prune($fid, $prune_sticky, $prune_date);
				sync_forum($fid);
			}
		}
		else
		{
			$prune_from = intval($prune_from);
			prune($prune_from, $prune_sticky, $prune_date);
			sync_forum($prune_from);
		}

		delete_orphans();

		($hook = get_hook('apr_prune_pre_redirect')) ? eval($hook) : null;

		redirect(forum_link('admin/prune.php'), $lang_admin_prune['Prune done'].' '.$lang_admin_common['Redirect']);
	}


	$prune_days = intval($_POST['req_prune_days']);
	if ($prune_days < 0)
		message($lang_admin_prune['Days to prune message']);

	$prune_date = time() - ($prune_days * 86400);
	$prune_from = $_POST['prune_from'];

	if ($prune_from != 'all')
	{
		$prune_from = intval($prune_from);

		// Fetch the forum name (just for cosmetic reasons)
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$prune_from
		);

		($hook = get_hook('apr_prune_comply_qr_get_forum_name')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$forum = forum_htmlencode($forum_db->result($result));
	}
	else
		$forum = 'all forums';

	// Count the number of topics to prune
	$query = array(
		'SELECT'	=> 'COUNT(t.id)',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.last_post<'.$prune_date.' AND t.moved_to IS NULL'
	);

	if ($prune_from != 'all')
		$query['WHERE'] .= ' AND t.forum_id='.$prune_from;
	if (!isset($prune_sticky))
		$query['WHERE'] .= ' AND t.sticky=0';

	($hook = get_hook('apr_prune_comply_qr_get_topic_count')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_topics = $forum_db->result($result);

	if (!$num_topics)
		message($lang_admin_prune['No days old message']);


	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link('admin/index.php')),
		array($lang_admin_common['Management'], forum_link('admin/reports.php')),
		array($lang_admin_prune['Prune topics'], forum_link('admin/prune.php')),
		$lang_admin_prune['Confirm prune heading']
	);

	($hook = get_hook('apr_prune_comply_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'management');
	define('FORUM_PAGE', 'admin-prune');
	require FORUM_ROOT.'header.php';

	// START SUBST - <forum_main>
	ob_start();


	($hook = get_hook('apr_prune_comply_output_start')) ? eval($hook) : null;

?>
 	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_prune['Prune details head'], ($forum == 'all forums') ? $lang_admin_prune['All forums'] : $forum ) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link('admin/prune.php') ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link('admin/prune.php').'?action=foo') ?>" />
				<input type="hidden" name="prune_days" value="<?php echo $prune_days ?>" />
				<input type="hidden" name="prune_sticky" value="<?php echo $prune_sticky ?>" />
				<input type="hidden" name="prune_from" value="<?php echo $prune_from ?>" />
			</div>
			<div class="ct-box">
				<p class="warn"><span><?php printf($lang_admin_prune['Prune topics info 1'], $num_topics, ($prune_sticky) ? ' ('.$lang_admin_prune['Include sticky'].')' : '') ?></span></p>
				<p class="warn"><span><?php printf($lang_admin_prune['Prune topics info 2'], $prune_days) ?></span></p>
			</div>
<?php ($hook = get_hook('apr_prune_comply_pre_buttons')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="prune_comply" value="<?php echo $lang_admin_prune['Prune topics'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('apr_prune_comply_end')) ? eval($hook) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<forum_main>', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <forum_main>

	require FORUM_ROOT.'admin/footer_adm.php';
}


else
{
	// Setup form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link('admin/index.php')),
		array($lang_admin_common['Management'], forum_link('admin/reports.php')),
		array($lang_admin_common['Prune topics'], forum_link('admin/prune.php'))
	);

	($hook = get_hook('apr_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'management');
	define('FORUM_PAGE', 'admin-prune');
	require FORUM_ROOT.'header.php';

	// START SUBST - <forum_main>
	ob_start();

	($hook = get_hook('apr_main_output_start')) ? eval($hook) : null;

?>
<div class="box">
	<div class="box-header with-border">
		<h3 class="box-title"><?php echo $lang_admin_prune['Prune settings head'] ?></h3>
			<div class="box-tools pull-right">
                <button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                <button class="btn btn-box-tool" data-widget="remove" data-toggle="tooltip" title="Remove"><i class="fa fa-times"></i></button>
            </div>
	</div>
	<div class="box-body">
			<p><?php echo $lang_admin_prune['Prune intro'] ?></p>
			<div class="alert alert-danger alert-dismissable">
			<p class="important"><?php echo $lang_admin_prune['Prune caution'] ?></p>
			<p class="important"><?php printf($lang_admin_common['Required warn'], '<em>'.$lang_admin_common['Required'].'</em>') ?></p></div>
	</div><!-- /.box-body -->
</div>
          
         
<div class="box">
            <div class="box-header with-border">
              <h3 class="box-title"><?php echo $lang_admin_prune['Prune settings head'] ?></h3>
              <div class="box-tools pull-right">
                <button class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                <button class="btn btn-box-tool" data-widget="remove" data-toggle="tooltip" title="Remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <form class="form-horizontal" method="post" accept-charset="utf-8" action="<?php echo forum_link('admin/prune.php') ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link('admin/prune.php').'?action=foo') ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('apr_pre_prune_fieldset')) ? eval($hook) : null; ?>
	<fieldset class="gc<?php echo ++$forum_page['group_count'] ?>">
		<legend>
			<?php echo $lang_admin_prune['Prune legend'] ?>
		</legend>
<?php ($hook = get_hook('apr_pre_prune_from')) ? eval($hook) : null; ?>
		<div class="form-group ic<?php echo ++$forum_page['item_count'] ?>">
			<label class="col-md-4 control-label" for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_admin_prune['Prune from'] ?></label>
			<div class="col-md-4">
				<select id="fld<?php echo $forum_page['fld_count'] ?>" class="form-control" name="prune_from">
<option value="all"><?php echo $lang_admin_prune['All forums'] ?></option>
<?php

	$query = array(
		'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'		=> 'c.id=f.cat_id'
			)
		),
		'WHERE'		=> 'f.redirect_url IS NULL',
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);

	($hook = get_hook('apr_qr_get_forum_list')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$cur_category = 0;
	while ($forum = $forum_db->fetch_assoc($result))
	{
		if ($forum['cid'] != $cur_category) // Are we still in the same category?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t\t".'<optgroup label="'.forum_htmlencode($forum['cat_name']).'">'."\n";
			$cur_category = $forum['cid'];
		}

		echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$forum['fid'].'">'.forum_htmlencode($forum['forum_name']).'</option>'."\n";
	}

?>
						</optgroup>
					</select>
			</div>
		</div>
<?php ($hook = get_hook('apr_pre_prune_days')) ? eval($hook) : null; ?>
		<div class="form-group ic<?php echo ++$forum_page['item_count'] ?>">
			<label class="col-md-4 control-label" for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_admin_prune['Days old'] ?> <em><?php echo $lang_admin_common['Required'] ?></em></label>
			<div class="col-md-4">
				<input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_prune_days" size="4" maxlength="4" class="form-control input-md">
			</div>
		</div>
<?php ($hook = get_hook('apr_pre_prune_sticky')) ? eval($hook) : null; ?>
		<div class="form-group ic<?php echo ++$forum_page['item_count'] ?>">
			<label class="col-md-4 control-label" for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_admin_prune['Prune sticky'] ?></label>
			<div class="col-md-4">
				<div class="checkbox">
					<label for="checkboxes-0">
						<input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="prune_sticky" value="1" checked="checked">
						<?php echo $lang_admin_prune['Prune sticky enable'] ?></label>
				</div>
			</div>
		</div>

			

<?php ($hook = get_hook('apr_pre_prune_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('apr_prune_fieldset_end')) ? eval($hook) : null; ?>


            </div><!-- /.box-body -->
			<div class="box-footer">
				<span class="submit"><input type="submit"  class="btn btn-primary" name="prune" value="<?php echo $lang_admin_prune['Prune topics'] ?>" /></span>
			</div>
</form>
          </div>
<?php

	($hook = get_hook('apr_end')) ? eval($hook) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<forum_main>', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <forum_main>

	require FORUM_ROOT.'admin/footer_adm.php';
}
