<?php
/**
 * @copyright Copyright (C) 2008 PunBB, partially based on code copyright (C) 2008 FluxBB.org
 * @modified Copyright (C) 2014-2017 Flazy.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package Flazy
 */

if (!defined('FORUM'))
	die;

/**
 * Отображения аватара участника.
 * @param int ID участника.
 * @param string Расширение установленого аватара (jpg, gif, png).
 * @param string Адрес электронной почты участника, для получения изображения с gravatar.com
 * @return string img тег аватара.
 */
function generate_avatar_markup($user_id, $filetypes, $user_email, $type_image)
{
	global $base_url, $forum_config, $forum_user;

	$return = ($hook = get_hook('fn_generate_avatar_markup_start')) ? eval($hook) : null;
	if ($return != null) {
        return $return;
    }
    if($type_image !== ''){
        $type = $type_image;
    }
    if ($filetypes) {
                $path = FORUM_ROOT.FORUM_AVATAR_DIR.$user_id.'.'.$filetypes;
                $img_size = getimagesize($path);
                $avatar_markup =  '<img src="'.$base_url.'/'.$path.'?m='.filemtime($path).'" '.$img_size[3].' alt="" class="'.$type.'"/>';
        }
	else
	{
            if (file_exists(FORUM_ROOT . 'style/' . $forum_user['style'] . '/avatar.gif')) {
            $default_avatar = $base_url . '/style/' . $forum_user['style'] . '/avatar.gif';
        } else {
            $default_avatar = $base_url . '/img/style/avatar.gif';
        }
        if ($forum_config['o_gravatar']) {
            $avatar_markup = '<img src="http://www.gravatar.com/avatar.php?gravatar_id=' . md5($user_email) . '&amp;d=' . $default_avatar . '&amp;rating=' . $forum_config['o_gravatar'] . '&amp;s=' . $forum_config['o_avatars_width'] . '" height="' . $forum_config['o_avatars_height'] . '" width="' . $forum_config['o_avatars_width'] . '" alt="" class="' . $type . '"/>';
        } else {
            $avatar_markup = '<img src="' . $default_avatar . '" height="' . $forum_config['o_avatars_height'] . '" width="' . $forum_config['o_avatars_width'] . '" alt="" class="' . $type . '"/>';
        }
    }

	($hook = get_hook('fn_generate_avatar_markup_end')) ? eval($hook) : null;

	return $avatar_markup;
}

define('FORUM_FUNCTIONS_GENERATE_AVATAR', 1);
