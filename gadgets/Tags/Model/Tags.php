<?php
/**
 * Tags Model
 *
 * @category    GadgetModel
 * @package     Tags
 * @author      Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/lesser.html
 */
class Tags_Model_Tags extends Jaws_Gadget_Model
{
    /**
     * Get a tag items
     *
     * @access  public
     * @param   int     $id   Tag id
     * @return  mixed   An array on success and Jaws_Error in case of errors
     */
    function GetTagItems($id)
    {
        $table = Jaws_ORM::getInstance()->table('tags');
        $table->select('tags_references.id as items_id:integer', 'gadget', 'action', 'reference:integer');
        $table->join('tags_references', 'tags_references.tag', 'tags.id');
        $table->where('tags.id', $id);
        $result = $table->orderBy('gadget asc')->fetchAll();
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error($result->getMessage());
        }
        return $result;
    }

    /**
     * Generates a tag cloud
     *
     * @access  public
     * @param   string  $gadget     Gadget name
     * @param   bool    $global     Just show global tags?
     * @return  mixed   An array on success and Jaws_Error in case of errors
     */
    function GenerateTagCloud($gadget, $global)
    {
        $table = Jaws_ORM::getInstance()->table('tags');

        $table->select('tags.id:integer', 'name', 'title', 'count(tags_references.gadget) as howmany:integer');
        $table->join('tags_references', 'tags_references.tag', 'tags.id', 'left');
        $table->where('tags_references.published', true);
        $table->and()->openWhere('tags_references.update_time', time(), '<')->or();
        $table->closeWhere('tags_references.update_time', null, 'is');
        if ($global) {
            $table->and()->where('tags.user', 0);
        } else {
            $table->and()->where('tags.user', $GLOBALS['app']->Session->GetAttribute('user'));
        }

        $table->groupBy('tags.id', 'tags.name', 'tags.title');

        if (!empty($gadget)) {
            $table->and()->where('gadget', $gadget);
        }

        $result = $table->orderBy('name asc')->fetchAll();
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error($result->getMessage());
        }
        return $result;
    }

    /**
     * Gets list of tag support gadgets
     *
     * @access  public
     * @return  array   List of tag-able gadgets
     */
    function GetTagableGadgets()
    {
        $cmpModel = Jaws_Gadget::getInstance('Components')->model->load('Gadgets');
        $gadgets = $cmpModel->GetGadgetsList(null, true, true);
        foreach ($gadgets as $gadget => $info) {
            if (is_file(JAWS_PATH. "gadgets/$gadget/Hooks/Tags.php")) {
                $gadgets[$gadget] = $info['title'];
                continue;
            }
            unset($gadgets[$gadget]);
        }

        return $gadgets;
    }

}