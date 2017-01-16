<?php
/**
 * Directory Gadget
 *
 * @category    Gadget
 * @package     Directory
 */
class Directory_Actions_Directory extends Jaws_Gadget_Action
{
    /**
     * Get Display action params
     *
     * @access  public
     * @return  array list of Display action params
     */
    function DirectoryLayoutParams()
    {
        $result[] = array('title' => _t('DIRECTORY_FILE_TYPE'), 'value' =>
            array(
                0 => _t('GLOBAL_ALL'),
                -1 => _t('DIRECTORY_FILE_TYPE_FOLDER'),
                Directory_Info::FILE_TYPE_TEXT    => _t('DIRECTORY_FILE_TYPE_TEXT'),
                Directory_Info::FILE_TYPE_IMAGE   => _t('DIRECTORY_FILE_TYPE_IMAGE'),
                Directory_Info::FILE_TYPE_AUDIO   => _t('DIRECTORY_FILE_TYPE_AUDIO'),
                Directory_Info::FILE_TYPE_VIDEO   => _t('DIRECTORY_FILE_TYPE_VIDEO'),
                Directory_Info::FILE_TYPE_ARCHIVE => _t('DIRECTORY_FILE_TYPE_ARCHIVE'),
                Directory_Info::FILE_TYPE_UNKNOWN => _t('DIRECTORY_FILE_TYPE_OTHER'),
            ));

        $result[] = array(
            'title' => _t('GLOBAL_ORDERBY'),
            'value' => array(
                1 => _t('GLOBAL_CREATETIME') . ' &uarr;',
                2 => _t('GLOBAL_CREATETIME') . ' &darr;',
            )
        );

        $result[] = array(
            'title' => _t('GLOBAL_COUNT'),
            'value' =>  $this->gadget->registry->fetch('files_limit')
        );

        return $result;
    }

    /**
     * Builds directory and file navigation UI
     *
     * @param   int     $type       File type (for normal action = null)
     * @param   int     $orderBy    Order by
     * @param   int     $limit      Forms limit
     * @access  public
     * @return  string  XHTML UI
     */
    function Directory($type = null, $orderBy = 0, $limit = 0)
    {
        $tpl = $this->gadget->template->load('Directory.html');
        $tpl->SetBlock('directory');

        $this->SetTitle(_t('DIRECTORY_ACTIONS_DIRECTORY'));
        $tpl->SetVariable('gadget_title', _t('DIRECTORY_ACTIONS_DIRECTORY'));

        $id = ($type == null)? (int)jaws()->request->fetch('id') : 0;
        if ($id == 0) {
            $tpl->SetVariable('content', $this->ListFiles(0, $type, $orderBy, $limit));
        } else {
            $model = $this->gadget->model->load('Files');
            $file = $model->GetFile($id);
            if (Jaws_Error::IsError($file) || empty($file)) {
                require_once JAWS_PATH . 'include/Jaws/HTTPError.php';
                return Jaws_HTTPError::Get(404);
            }
            if ($file['is_dir']) {
                $tpl->SetVariable('content', $this->ListFiles($id, $type));
            } else {
                $tpl->SetVariable('content', $this->ViewFile($file));
            }
            $tpl->SetVariable('root', _t('DIRECTORY_HOME'));
            $tpl->SetVariable('root_url', $this->gadget->urlMap('Directory'));
            $tpl->SetVariable('path', $this->GetPath($id));
        }

        $tpl->SetVariable('upload', _t('DIRECTORY_UPLOAD_FILE'));
        if ($id > 0) {
            $tpl->SetVariable('upload_url', $this->gadget->urlMap('UploadFileUI', array('parent' => $id)));
        } else {
            $tpl->SetVariable('upload_url', $this->gadget->urlMap('UploadFileUI'));
        }

        $tpl->ParseBlock('directory');
        return $tpl->Get();
    }

    /**
     * Fetches and displays list of dirs/files
     *
     * @access  public
     * @param   int     $parent
     * @param   null    $type
     * @param   int     $orderBy    Order by
     * @param   int     $limit      Forms limit
     * @return string HTML content
     */
    function ListFiles($parent = 0, $type = null, $orderBy = 0, $limit = 0)
    {
        $filters = jaws()->request->fetch(
            array('filter_file_type', 'filter_file_size', 'filter_from_date', 'filter_to_date', 'filter_order'),
            'post');

        $isLayoutAction = false;
        // Layout action
        if($GLOBALS['app']->requestedActionMode == ACTION_MODE_LAYOUT) {
            $isLayoutAction = true;
            $page = 0;
            $params = array();

            if ($type == '-1') {
                $params['is_dir'] = true;
            } else {
                $params['file_type'] = $type;
            }
        } else {
            $params = jaws()->request->fetch(array('type', 'page'), 'get');
            $page = (int)$params['page'];
            if ($params['type'] !== null) {
                $params['file_type'] = $params['type'];
            }
            unset($params['type']);
        }

        $params['limit'] = ($limit > 0) ? $limit : (int)$this->gadget->registry->fetch('items_per_page');
        $params['offset'] = ($page == 0)? 0 : $params['limit'] * ($page - 1);
        $params['parent'] = $parent;
        $params['published'] = true;

        $user = jaws()->request->fetch('user', 'get');
        if (!empty($user)) {
            $params['user'] = $user;
        }

        // check filters
        if (!empty($filters['filter_file_type']) && empty($type)) {
            if ($filters['filter_file_type'] == '-1') {
                $params['is_dir'] = true;
            } else {
                $params['file_type'] = $filters['filter_file_type'];
            }
        }
        if (!empty($filters['filter_file_size'])) {
            $params['file_size'] = ($filters['filter_file_size'] == '0') ?
                null : explode(',', $filters['filter_file_size']);
        }

        $jdate = Jaws_Date::getInstance();
        $from_date = $to_date = '';
        if (!empty($filters['filter_from_date'])) {
            $from_date = $jdate->ToBaseDate(preg_split('/[- :]/', $filters['filter_from_date']));
            $from_date = $GLOBALS['app']->UserTime2UTC($from_date['timestamp']);
        }
        if (!empty($filters['filter_to_date'])) {
            $to_date = $jdate->ToBaseDate(preg_split('/[- :]/', $filters['filter_to_date'] . ' 23:59:59'));
            $to_date = $GLOBALS['app']->UserTime2UTC($to_date['timestamp']);
        }
        $params['date'] = array($from_date, $to_date);
        if (!empty($filters['filter_order'])) {
            $orderBy = (int)$filters['filter_order'];
        }

        $calType = strtolower($this->gadget->registry->fetch('calendar', 'Settings'));
        $calLang = strtolower($this->gadget->registry->fetch('admin_language', 'Settings'));
        if ($calType != 'gregorian') {
            $GLOBALS['app']->Layout->addScript("libraries/piwi/piwidata/js/jscalendar/$calType.js");
        }
        $GLOBALS['app']->Layout->addScript('libraries/piwi/piwidata/js/jscalendar/calendar.js');
        $GLOBALS['app']->Layout->addScript('libraries/piwi/piwidata/js/jscalendar/calendar-setup.js');
        $GLOBALS['app']->Layout->addScript("libraries/piwi/piwidata/js/jscalendar/lang/calendar-$calLang.js");
        $GLOBALS['app']->Layout->addLink('libraries/piwi/piwidata/js/jscalendar/calendar-blue.css');
        $this->AjaxMe('index.js');

        $tpl = $this->gadget->template->load('Directory.html');
        $tpl->SetBlock('filters');

        $tpl->SetVariable('lbl_type', _t('DIRECTORY_FILE_TYPE'));
        $tpl->SetVariable('lbl_size', _t('DIRECTORY_FILE_SIZE'));
        $tpl->SetVariable('lbl_from_date', _t('DIRECTORY_FILE_FROM_DATE'));
        $tpl->SetVariable('lbl_to_date', _t('DIRECTORY_FILE_TO_DATE'));
        $tpl->SetVariable('lbl_search', _t('GLOBAL_SEARCH'));
        $tpl->SetVariable('lbl_order', _t('GLOBAL_ORDERBY'));
        $tpl->SetVariable('lbl_create_time', _t('GLOBAL_CREATETIME'));

        $tpl->SetVariable('lbl_folder', _t('DIRECTORY_FILE_TYPE_FOLDER'));
        $tpl->SetVariable('lbl_text', _t('DIRECTORY_FILE_TYPE_TEXT'));
        $tpl->SetVariable('lbl_image', _t('DIRECTORY_FILE_TYPE_IMAGE'));
        $tpl->SetVariable('lbl_audio', _t('DIRECTORY_FILE_TYPE_AUDIO'));
        $tpl->SetVariable('lbl_video', _t('DIRECTORY_FILE_TYPE_VIDEO'));
        $tpl->SetVariable('lbl_archive', _t('DIRECTORY_FILE_TYPE_ARCHIVE'));
        $tpl->SetVariable('lbl_other', _t('DIRECTORY_FILE_TYPE_OTHER'));

        // file type
        $fileTypes = array();
        $fileTypes[] = array('id' => 0, 'title' => _t('GLOBAL_ALL'));
        $fileTypes[] = array('id' => -1, 'title' => _t('DIRECTORY_FILE_TYPE_FOLDER'));
        $fileTypes[] = array('id' => Directory_Info::FILE_TYPE_TEXT, 'title' => _t('DIRECTORY_FILE_TYPE_TEXT'));
        $fileTypes[] = array('id' => Directory_Info::FILE_TYPE_IMAGE, 'title' => _t('DIRECTORY_FILE_TYPE_IMAGE'));
        $fileTypes[] = array('id' => Directory_Info::FILE_TYPE_AUDIO, 'title' => _t('DIRECTORY_FILE_TYPE_AUDIO'));
        $fileTypes[] = array('id' => Directory_Info::FILE_TYPE_VIDEO, 'title' => _t('DIRECTORY_FILE_TYPE_VIDEO'));
        $fileTypes[] = array('id' => Directory_Info::FILE_TYPE_ARCHIVE, 'title' => _t('DIRECTORY_FILE_TYPE_ARCHIVE'));
        $fileTypes[] = array('id' => Directory_Info::FILE_TYPE_UNKNOWN, 'title' => _t('DIRECTORY_FILE_TYPE_OTHER'));
        foreach ($fileTypes as $fileType) {
            $tpl->SetBlock('filters/file_type');
            $tpl->SetVariable('value', $fileType['id']);
            $tpl->SetVariable('title', $fileType['title']);

            $tpl->SetVariable('selected', '');
            if ($fileType['id'] == -1 && isset($params['is_dir']) && $params['is_dir'] == true) {
                $tpl->SetVariable('selected', 'selected');
            } else if (isset($params['file_type']) && $params['file_type'] == $fileType['id']) {
                $tpl->SetVariable('selected', 'selected');
            }
            $tpl->ParseBlock('filters/file_type');
        }

        // file size
        $fileSizes = array();
        $fileSizes[] = array('id' => '0', 'title' => _t('GLOBAL_ALL'));
        $fileSizes[] = array('id' => '0,10', 'title' => '0 - 10 KB');
        $fileSizes[] = array('id' => '10,100', 'title' => '10 - 100 KB');
        $fileSizes[] = array('id' => '100,1024', 'title' => '100 KB - 1 MB');
        $fileSizes[] = array('id' => '1024,16384', 'title' => '1 MB - 16 MB');
        $fileSizes[] = array('id' => '16384,131072', 'title' => '16 MB - 128 MB');
        $fileSizes[] = array('id' => '131072,', 'title' => '>> 128 MB');
        foreach($fileSizes as $fileSize) {
            $tpl->SetBlock('filters/file_size');
            $tpl->SetVariable('value', $fileSize['id']);
            $tpl->SetVariable('title', $fileSize['title']);

            $tpl->SetVariable('selected', '');
            if ($filters['filter_file_size'] == $fileSize['id']) {
                $tpl->SetVariable('selected', 'selected');
            }
            $tpl->ParseBlock('filters/file_size');
        }

        // Start date
        $cal_type = $this->gadget->registry->fetch('calendar', 'Settings');
        $cal_lang = $this->gadget->registry->fetch('site_language', 'Settings');
        $datePicker =& Piwi::CreateWidget('DatePicker', 'filter_from_date', $filters['filter_from_date']);
        $datePicker->setCalType($cal_type);
        $datePicker->setLanguageCode($cal_lang);
        $datePicker->setDateFormat('%Y-%m-%d');
        $datePicker->setStyle('width:80px');
        $tpl->SetVariable('from_date', $datePicker->Get());

        // End date
        $datePicker =& Piwi::CreateWidget('DatePicker', 'filter_to_date', $filters['filter_to_date']);
        $datePicker->setDateFormat('%Y-%m-%d');
        $datePicker->setCalType($cal_type);
        $datePicker->setLanguageCode($cal_lang);
        $datePicker->setStyle('width:80px');
        $tpl->SetVariable('to_date', $datePicker->Get());

        $tpl->SetVariable('order_selected_' . $orderBy, 'selected');
        $tpl->ParseBlock('filters');


        $model = $this->gadget->model->load('Files');
        $files = $model->GetFiles($params, false, $orderBy);
        if (Jaws_Error::IsError($files)) {
            return '';
        }
        if (empty($files)) {
            $tpl->SetBlock('message');
            $tpl->SetVariable('msg', _t('DIRECTORY_INFO_NO_FILES'));
            $tpl->ParseBlock('message');
            return $tpl->Get();
        }
        $count = $model->GetFiles($params, true, $orderBy);

        $tpl->SetBlock('files');
        $tpl->SetVariable('lbl_title', _t('DIRECTORY_FILE_TITLE'));
        $tpl->SetVariable('lbl_created', _t('DIRECTORY_FILE_CREATED'));
        $tpl->SetVariable('lbl_modified', _t('DIRECTORY_FILE_MODIFIED'));
        $tpl->SetVariable('lbl_type', _t('DIRECTORY_FILE_TYPE'));
        $tpl->SetVariable('lbl_size', _t('DIRECTORY_FILE_SIZE'));

        $tpl->SetVariable('site_url', $GLOBALS['app']->getSiteURL('/'));
        $theme = $GLOBALS['app']->GetTheme();
        $iconUrl = is_dir($theme['url'] . 'mimetypes')? $theme['url'] . 'mimetypes/' : 'images/mimetypes/';
        $icons = array(
            null => 'folder',
            0 => 'file-generic',
            1 => 'text-generic',
            2 => 'image-generic',
            3 => 'audio-generic',
            4 => 'video-generic',
            5 => 'package-generic'
        );
        $objDate = Jaws_Date::getInstance();
        foreach ($files as $file) {
            $url = $this->gadget->urlMap('Directory', array('id' => $file['id']));
            $tpl->SetBlock('files/file');
            $tpl->SetVariable('url', $url);
            $tpl->SetVariable('title', $file['title']);
            $tpl->SetVariable('type', empty($file['mime_type'])? '-' : $file['mime_type']);
            $tpl->SetVariable('size', Jaws_Utils::FormatSize($file['file_size']));
            $tpl->SetVariable('created', $objDate->Format($file['create_time'], 'n/j/Y g:i a'));
            $tpl->SetVariable('modified', $objDate->Format($file['update_time'], 'n/j/Y g:i a'));

            $thumbnailURL = $model->GetThumbnailURL($file['host_filename']);
            if (!empty($thumbnailURL)) {
                $tpl->SetVariable('icon', $model->GetThumbnailURL($file['host_filename']));
            } else {
                $tpl->SetVariable('icon', $iconUrl . $icons[$file['file_type']] . '.png');
            }

            $tpl->ParseBlock('files/file');
        }

        // Pagination
        if (!$isLayoutAction && $params['limit'] > 0) {
            $args = array();
            if ($parent > 0) {
                $args['id'] = $parent;
            }
            $this->gadget->action->load('Navigation')->pagination(
                $tpl,
                $page,
                $params['limit'],
                $count,
                'Directory',
                $args
            );
        }

        $tpl->ParseBlock('files');
        return $tpl->Get();
    }

    /**
     * Displays file properties
     *
     * @access  public
     * @return  string  HTML content
     */
    function ViewFile($file)
    {
        $tpl = $this->gadget->template->load('Directory.html');
        $tpl->SetBlock('file');

        $tpl->SetVariable('lbl_title', _t('DIRECTORY_FILE_TITLE'));
        $tpl->SetVariable('lbl_desc', _t('DIRECTORY_FILE_DESC'));
        $tpl->SetVariable('lbl_filename', _t('DIRECTORY_FILE_FILENAME'));
        $tpl->SetVariable('lbl_type', _t('DIRECTORY_FILE_TYPE'));
        $tpl->SetVariable('lbl_size', _t('DIRECTORY_FILE_SIZE'));
        $tpl->SetVariable('lbl_bytes', _t('DIRECTORY_BYTES'));
        $tpl->SetVariable('lbl_created', _t('DIRECTORY_FILE_CREATED'));
        $tpl->SetVariable('lbl_modified', _t('DIRECTORY_FILE_MODIFIED'));
        $tpl->SetVariable('lbl_download', _t('DIRECTORY_DOWNLOAD'));

        $objDate = Jaws_Date::getInstance();
        $file['created'] = $objDate->Format($file['create_time'], 'n/j/Y g:i a');
        $file['modified'] = $objDate->Format($file['update_time'], 'n/j/Y g:i a');
        $file['type'] = empty($file['mime_type'])? '-' : $file['mime_type'];
        $file['size'] = Jaws_Utils::FormatSize($file['file_size']);
        $file['download'] = $this->gadget->urlMap('Download', array('id' => $file['id']));
        foreach ($file as $key => $value) {
            $tpl->SetVariable($key, $value);
        }

        // display tags
        if (Jaws_Gadget::IsGadgetInstalled('Tags')) {
            $tagsHTML = Jaws_Gadget::getInstance('Tags')->action->load('Tags');
            $tagsHTML->loadReferenceTags('Directory', 'file', $file['id'], $tpl, 'file');
        }

        // display file
        $fileInfo = pathinfo($file['host_filename']);
        if (isset($fileInfo['extension'])) {
            $ext = $fileInfo['extension'];
            $type = '';
            if ($ext === 'txt') {
                $type = 'text';
            } else if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'svg'))) {
                $type = 'image';
            } else if (in_array($ext, array('wav', 'mp3'))) {
                $type = 'audio';
            } else if (in_array($ext, array('webm', 'mp4', 'ogg'))) {
                $type = 'video';
            }
            if ($type != '') {
                $tpl->SetVariable('preview', $this->PlayMedia($file, $type));
            }
        }

        // display thumbnail
        $model = $this->gadget->model->load('Files');
        $tpl->SetVariable('thumbnail', $model->GetThumbnailURL($file['host_filename']));

        // display comments/comment-form
        if (Jaws_Gadget::IsGadgetInstalled('Comments')) {
            $allow_comments = $this->gadget->registry->fetch('allow_comments', 'Comments');

            $cHTML = Jaws_Gadget::getInstance('Comments')->action->load('Comments');
            $tpl->SetVariable('comments', $cHTML->ShowComments('Directory', 'File', $file['id'],
                array('action' => 'Directory', 'params' => array('id' => $file['id']))));

            if ($allow_comments == 'true') {
                $redirect_to = $this->gadget->urlMap('Directory', array('id' => $file['id']));
                $tpl->SetVariable('comment-form', $cHTML->ShowCommentsForm('Directory', 'File', $file['id'], $redirect_to));
            } elseif ($allow_comments == 'restricted') {
                $login_url = $GLOBALS['app']->Map->GetURLFor('Users', 'LoginBox');
                $register_url = $GLOBALS['app']->Map->GetURLFor('Users', 'Registration');
                $tpl->SetVariable('comment-form', _t('COMMENTS_COMMENTS_RESTRICTED', $login_url, $register_url));
            }
        }

        // Show like rating
        if (Jaws_Gadget::IsGadgetInstalled('Rating')) {
            $ratingHTML = Jaws_Gadget::getInstance('Rating')->action->load('RatingTypes');
            $ratingHTML->loadReferenceLike('Directory', 'File', $file['id'], 0, $tpl, 'file');
        }

        // display subscription if installed
//        if (Jaws_Gadget::IsGadgetInstalled('Subscription')) {
//            $sHTML = Jaws_Gadget::getInstance('Subscription')->action->load('Subscription');
//            $tpl->SetVariable('subscription', $sHTML->ShowSubscription('Directory', 'Folder', $e['id']));
//        }

        $tpl->ParseBlock('file');
        return $tpl->Get();
    }

    /**
     * Fetches path of a file/directory
     *
     * @access  public
     * @return  array   Directory hierarchy
     */
    function GetPath($id)
    {
        $path = '';
        $pathArr = array();
        $model = $this->gadget->model->load('Files');
        $model->GetPath($id, $pathArr);
        foreach(array_reverse($pathArr) as $i => $p) {
            $url = $this->gadget->urlMap('Directory', array('id' => $p['id']));
            $path .= ($i == count($pathArr) - 1)?
                ' > ' . $p['title'] :
                " > <a href='$url'>" . $p['title'] . '</a>';
        }
        return $path;
    }

    /**
     * Displays file
     *
     * @access  public
     * @return  array   Response array
     */
    function PlayMedia($file, $type)
    {
        $tpl = $this->gadget->template->loadAdmin('Media.html');
        $tpl->SetBlock($type);
        if ($type === 'text') {
            $filename = JAWS_DATA . 'directory/' . $file['host_filename'];
            if (file_exists($filename)) {
                $tpl->SetVariable('text', file_get_contents($filename));
            }
        } else {
            $tpl->SetVariable('url', $this->gadget->urlMap('Download', array('id' => $file['id'])));
        }
        $tpl->ParseBlock($type);

        return $this->gadget->ParseText($tpl->get(), 'Directory', 'index');
    }

    /**
     * Downloads(streams) file
     *
     * @access  public
     * @return  mixed   File data or Jaws_Error
     */
    function Download()
    {
        $id = jaws()->request->fetch('id');
        if (is_null($id)) {
            return Jaws_HTTPError::Get(500);
        }
        $id = (int)$id;
        $model = $this->gadget->model->load('Files');

        // Validate file
        $file = $model->GetFile($id);
        if (Jaws_Error::IsError($file)) {
            return Jaws_HTTPError::Get(500);
        }
        if (empty($file) || empty($file['user_filename'])) {
            return Jaws_HTTPError::Get(404);
        }

        // Check for file existence
        $filename = JAWS_DATA . 'directory/' . $file['host_filename'];
        if (!file_exists($filename)) {
            return Jaws_HTTPError::Get(404);
        }

        // Stream file
        if (!Jaws_Utils::Download($filename, $file['user_filename'], $file['mime_type'])) {
            return Jaws_HTTPError::Get(500);
        }

        return true;
    }
}