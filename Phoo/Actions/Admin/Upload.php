<?php
/**
 * Phoo Gadget
 *
 * @category   GadgetAdmin
 * @package    Phoo
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Raul Murciano <raul@murciano.net>
 * @copyright  2004-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Phoo_Actions_Admin_Upload extends Phoo_AdminHTML
{
    /**
     * Displays a form for adding new images.
     *
     * @access  public
     * @return  string   XHTML template content
     */
    function UploadPhotos()
    {
        $this->gadget->CheckPermission('AddPhotos');
        $this->AjaxMe('script.js');

        $request =& Jaws_Request::getInstance();
        $album = $request->get('album', 'get');

        $model = $GLOBALS['app']->LoadGadget('Phoo', 'Model', 'Albums');
        $tpl = $this->gadget->loadTemplate('UploadPhotos.html');
        $tpl->SetBlock('upload');
        $tpl->SetVariable('menubar', $this->MenuBar('UploadPhotos'));

        include_once JAWS_PATH . 'include/Jaws/Widgets/FieldSet.php';
        $phooFieldset = new Jaws_Widgets_FieldSet(_t('PHOO_UPLOAD_PHOTOS'));
        $phooFieldset->SetDirection('vertical');
        $phooFieldset->SetId('phoo_fieldset');
        $phooForm =& Piwi::CreateWidget('Form',
            BASE_SCRIPT . '?gadget=Phoo',
            'post',
            'multipart/form-data');
        $phooForm->Add(Piwi::CreateWidget('HiddenEntry', 'MAX_FILE_SIZE', '15000000'));
        $phooForm->Add(Piwi::CreateWidget('HiddenEntry', 'gadget', 'Phoo'));
        $phooForm->Add(Piwi::CreateWidget('HiddenEntry', 'action', 'UploadPhotosStep2'));

        $albumcombo =& Piwi::CreateWidget('Combo', 'album', _t('PHOO_ALBUM'));
        $albums = $model->GetAlbums('name', 'ASC');
        if (!Jaws_Error::IsError($albums) && !empty($albums)) {
            $date = $GLOBALS['app']->loadDate();
            foreach ($albums as $a) {
                // FIXME: Ugly hack to add title to albumcombo
                $o =& Piwi::CreateWidget('ComboOption', $a['id'], $a['name']);
                $o->SetTitle(_t('PHOO_NUM_PHOTOS_ALBUM', $a['howmany']) . ' / '.
                _t('PHOO_ALBUM_CREATION_DATE', $date->Format($a['createtime'])));
                $albumcombo->_options[$a['id']] = $o;
            }
        }
        $albumcombo->SetDefault((int)$album);
        $phooFieldset->Add($albumcombo);
        for ($i = 1; $i <= 5; $i++) {
            $imageEntry =& Piwi::CreateWidget('FileEntry', 'photo'.$i);
            $imageEntry->SetTitle(_t('PHOO_PHOTO').' '.$i);
            $phooFieldset->Add($imageEntry);
        }

        $addEntryButton =& Piwi::CreateWidget('Button', 'addEntryButton', _t('PHOO_ADD_ANOTHER_PHOTO'), STOCK_ADD);
        $addEntryButton->AddEvent(ON_CLICK, "addEntry('" . _t('PHOO_PHOTO') . "');");
        $addEntryUrl = '<span id="phoo_addentry6"><div><a href="#" onclick="addEntry(\'' . _t('PHOO_PHOTO') . '\');">' . _t('PHOO_ADD_ANOTHER_PHOTO') . '</a></div></span>';
        $addEntryArea = '<span id="phoo_addentry6"><div>' . $addEntryButton->Get() . '</div></span>';
        $addEntry =& Piwi::CreateWidget('StaticEntry', $addEntryArea);
        $phooFieldset->Add($addEntry);
        $buttonbox =& Piwi::CreateWidget('HBox');
        $buttonbox->SetStyle(_t('GLOBAL_LANG_DIRECTION')=='rtl'?'float: left;' : 'float: right;'); //hig style
        $submit =& Piwi::CreateWidget('Button', 'uploadphotos', _t('PHOO_UPLOAD_PHOTOS'), STOCK_SAVE);
        $submit->SetSubmit();
        $buttonbox->Add($submit);
        $phooForm->Add($phooFieldset);
        $phooForm->Add($buttonbox);

        $tpl->SetVariable('form', $phooForm->Get());

        $tpl->ParseBlock('upload');
        return $tpl->Get();
    }

    /**
     * Saves a new image using input data from UploadPhotos
     * If required any new albums will be created
     *
     * @access  public
     * @see Phoo::UploadPhotos()
     * @see Phoo_Model::AddEntryToAlbum()
     * @see Phoo_Model::AddCategoryToEntry()
     * @see Phoo_Model::NewAlbum()
     */
    function UploadPhotosStep2()
    {
        $uModel = $GLOBALS['app']->LoadGadget('Phoo', 'AdminModel', 'Upload');
        $pModel = $GLOBALS['app']->LoadGadget('Phoo', 'AdminModel', 'Photos');
        $aModel = $GLOBALS['app']->LoadGadget('Phoo', 'Model', 'Albums');
        $files = $uModel->UnpackFiles($_FILES);

        $request =& Jaws_Request::getInstance();
        $album   = (int)$request->get('album', 'post');
        $extra_params = $request->get('extra_params', 'post');

        $failures = array();
        $uploadedImages = array();
        $user_id = $GLOBALS['app']->Session->GetAttribute('user');
        $album_data = $aModel->getAlbumInfo($album);
        if (Jaws_Error::IsError($album_data) || empty($album_data)) {
            $GLOBALS['app']->Session->PushLastResponse($album_data->getMessage());
            Jaws_Header::Location(BASE_SCRIPT . '?gadget=Phoo');
            exit;
        }
        for ($i = 1; $i <= count($files); $i++) {
            if (!isset($files['photo'.$i])) {
                continue;
            }

            $filename = $files['photo'.$i]['name'];
            if (!empty($filename)) {
                $exploded = explode('.', $filename);
                $ext  = array_pop($exploded);
                $name = basename($filename, '.'.$ext);
                $id = $pModel->NewEntry($user_id, $files['photo'.$i], $name, '', true, $album_data);
                if (!Jaws_Error::IsError($id)) {
                    $uploadedImages[] = $id;
                } else {
                    $failures[] = _t('PHOO_UPLOAD_FAILURE').' <strong>'.$name.'.'.$ext.'</strong>';
                }
            }
        }

        // Assign to album
        if (count($uploadedImages) > 0) {
            foreach ($uploadedImages as $img) {
                $res = $pModel->AddEntryToAlbum($img, $album);
                if (Jaws_Error::IsError($res)) {
                    ///FIXME: This is a unacceptable solution
                    Jaws_Header::Location(BASE_SCRIPT . '?gadget=Phoo');
                }
            }
        }

        $GLOBALS['app']->Session->SetAttribute('failures', $failures);
        if (empty($extra_params)) {
            Jaws_Header::Location(BASE_SCRIPT . '?gadget=Phoo&album=' . $album);
        } else {
            Jaws_Header::Location(BASE_SCRIPT . '?gadget=Phoo&action=BrowsePhoo&album=' . $album . html_entity_decode($extra_params));
        }

    }

}