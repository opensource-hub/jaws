<?php
require_once JAWS_PATH . 'gadgets/Comments/Model.php';
/**
 * Comments Gadget Admin
 *
 * @category   GadgetModel
 * @package    Comments
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2005-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Comments_AdminModel extends Comments_Model
{
    /**
     * Updates a comment
     *
     * @param   string  $gadget  Gadget's name
     * @param   int     $id      Comment's ID
     * @param   string  $name    Author's name
     * @param   string  $email   Author's email
     * @param   string  $url     Author's url
     * qparam   string  $title   Author's title message
     * @param   string  $message Author's message
     * @param   string  $permalink Permanent link to resource
     * @param   string  $status  Comment status
     * @return  bool   True if sucess or Jaws_Error on any error
     * @access  public
     */
    function UpdateComment($gadget, $id, $name, $email, $url, $title, $message, $permalink, $status)
    {
        $sql = '
            UPDATE [[comments]] SET
                [name]    = {name},
                [email]   = {email},
                [url]     = {url},
                [msg_txt] = {message},
                [msg_key] = {message_key},
                [title]   = {title},
                [status]  = {status}
            WHERE
                [id] = {id}
              AND
                [gadget] = {gadget}';

        $params = array();
        $params['id']          = $id;
        $params['gadget']      = $gadget;
        $params['name']        = $name;
        $params['email']       = $email;
        $params['url']         = $url;
        $params['title']       = $title;
        $params['message']     = $message;
        $params['message_key'] = md5($message);
        $params['status']      = $status;

        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('GLOBAL_COMMENT_ERROR_NOT_UPDATED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_UPDATED'), _t('COMMENTS_NAME'));
        }

        $origComment = $this->GetComment($gadget, $id);
        if (($status == COMMENT_STATUS_SPAM ||$origComment['status'] == COMMENT_STATUS_SPAM) &&
            $origComment['status'] != $status)
        {
            $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'AdminModel');
            if ($status == COMMENT_STATUS_SPAM) {
                $mPolicy->SubmitSpam($permalink, $gadget, $name, $email, $url, $message);
            } else {
                $mPolicy->SubmitHam($permalink, $gadget, $name, $email, $url, $message);
            }
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('GLOBAL_COMMENT_UPDATED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Deletes a comment
     *
     * @param   string  $gadget Gadget's name
     * @param   int     $id     Comment's ID
     * @return  bool   True if sucess or Jaws_Error on any error
     * @access  public
     */
    function DeleteComment($gadget, $id)
    {
        $origComment = $this->GetComment($gadget, $id);
        if (Jaws_Error::IsError($id)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }

        if (empty($origComment)) {
            return false;
        }

        $params = array();
        $params['id']       = $id;
        $params['gadget']   = $gadget;
        $params['parent']   = $origComment['parent'];
        $params['gadgetId'] = $origComment['gadget_reference'];
        $origComment = null;

        $sql = 'DELETE FROM [[comments]] WHERE [id] = {id}';
        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }

        // Up childs to deleted parent level...
        $sql = "UPDATE [[comments]]
                SET [parent] = {parent}
                WHERE [parent] = {id}";
        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }

        // Count new childs...
        $sql = 'SELECT COUNT(*) AS replies
                FROM [[comments]]
                WHERE [parent] = {parent}';
        $row = $GLOBALS['db']->queryRow($sql, $params);
        if (Jaws_Error::IsError($row)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }
        $params['replies'] = $row['replies'];

        // Update replies field in parent...
        $sql = '
             UPDATE [[comments]] SET
                 [replies] = {replies}
             WHERE
                 [id] = {parent}
               AND
                 [gadget_reference] = {gadgetId}';
            // TODO: I dont know why use "gadget" in this query ( and also gadget_reference ) !!
               //AND
               // [gadget] = {gadget}';

        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }

        return true;
    }

    /**
     * Deletes all comment from a given gadget reference
     *
     * @param   string  $gadget Gadget's name
     * @param   int     $id     Gadget id reference
     * @return  bool   True if sucess or Jaws_Error on any error
     * @access  public
     */
    function DeleteCommentsByReference($gadget, $id)
    {
        $params = array();
        $params['id']       = $id;
        $params['gadget']   = $gadget;
        $sql = "DELETE FROM [[comments]]
                WHERE
                    [gadget_reference] = {id}
                AND
                    [gadget] = {gadget}";
        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }
    }

    /**
     * Mark as a different status several comments
     *
     * @access  public
     * @param   string $gadget  Gadget's name
     * @param   array   $ids     Id's of the comments to mark as spam
     * @param   string  $status  New status (spam by default)
     */
    function MarkAs($gadget, $ids, $status = 'spam')
    {
        if (count($ids) == 0) return;

        $list = implode(',', $ids);

        if (!in_array($status, array('approved', 'waiting', 'spam'))) {
            $status = COMMENT_STATUS_SPAM;
        }

        // Update status...
        $sql = "UPDATE [[comments]] SET [status] = {status} WHERE [id] IN (" . $list . ")";
        $GLOBALS['db']->query($sql, array('status' => $status));

        // FIXME: Update replies counter...
        if ($status == COMMENT_STATUS_SPAM) {
            $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'AdminModel');
            // Submit spam...
            $sql =
                "SELECT
                    [id], [gadget_reference], [gadget], [parent], [name], [email], [url], [ip],
                    [title], [msg_txt], [replies], [status], [createtime]
                FROM [[comments]]
                WHERE [id] IN (" . $list . ")";

            $items = $GLOBALS['db']->queryAll($sql);
            foreach ($items as $i) {
                if ($i['status'] != COMMENT_STATUS_SPAM) {
                    // FIXME Get $permalink
                    $permalink = '';
                    $mPolicy->SubmitSpam($permalink, $gadget, $i['name'], $i['email'], $i['url'], $i['message']);
                }
            }
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('GLOBAL_COMMENT_MARKED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Deletes all comments of a certain gadget
     *
     * @access public
     * @param   string $gadget Gadget's name
     * @return mixed   True on success and Jaws_Error on failure
     */
    function DeleteCommentsOfGadget($gadget)
    {
        $params           = array();
        $params['gadget'] = $gadget;

        $sql = '
           DELETE FROM [[comments]]
           WHERE [gadget] = {gadget}';

        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_NOT_DELETED'), _t('COMMENTS_NAME'));
        }

        return true;
    }

    /**
     * Gets a list of comments that match a certain filter.
     *
     * See Filter modes for more info
     *
     * @access  public
     * @param   string  $gadget     Gadget's name
     * @param   string  $filterMode Which mode should be used to filter
     * @param   string  $filterData Data that will be used in the filter
     * @param   string  $status     Spam status (approved, waiting, spam)
     * @param   mixed   $limit      Limit of data (numeric/boolean: no limit)
     * @return  array   Returns an array with of filtered comments or Jaws_Error on error
     */
    function GetFilteredComments($gadget, $filterMode, $filterData, $status, $limit)
    {
        if (
            $filterMode != COMMENT_FILTERBY_REFERENCE &&
            $filterMode != COMMENT_FILTERBY_STATUS &&
            $filterMode != COMMENT_FILTERBY_IP
            ) {
            $filterData = '%'.$filterData.'%';
        }

        $params = array();
        $params['filterData'] = $filterData;
        $params['gadget'] = $gadget;

        $sql = '
            SELECT
                [id],
                [gadget_reference],
                [gadget],
                [parent],
                [name],
                [email],
                [url],
                [ip],
                [title],
                [msg_txt],
                [replies],
                [status],
                [createtime]
            FROM [[comments]]';

        if (!is_null($gadget) && ($gadget != -1)) {
            $sql .= ' WHERE [gadget] = {gadget}';
        }

        switch ($filterMode) {
        case COMMENT_FILTERBY_REFERENCE:
            $sql.= ' AND [gadget_reference] = {filterData}';
            break;
        case COMMENT_FILTERBY_NAME:
            $sql.= ' AND [name] LIKE {filterData}';
            break;
        case COMMENT_FILTERBY_EMAIL:
            $sql.= ' AND [email] LIKE {filterData}';
            break;
        case COMMENT_FILTERBY_URL:
            $sql.= ' AND [url] LIKE {filterData}';
            break;
        case COMMENT_FILTERBY_TITLE:
            $sql.= ' AND [title] LIKE {filterData}';
            break;
        case COMMENT_FILTERBY_IP:
            $sql.= ' AND [ip] LIKE {filterData}';
            break;
        case COMMENT_FILTERBY_MESSAGE:
            $sql.= ' AND [msg_txt] LIKE {filterData}';
            break;
        case COMMENT_FILTERBY_VARIOUS:
            $sql.= ' AND ([name] LIKE {filterData}';
            $sql.= ' OR [email] LIKE {filterData}';
            $sql.= ' OR [url] LIKE {filterData}';
            $sql.= ' OR [title] LIKE {filterData}';
            $sql.= ' OR [msg_txt] LIKE {filterData})';
            break;
        default:
            if (is_bool($limit)) {
                $limit = false;
                //By default we get the last 20 comments
                $result = $GLOBALS['db']->setLimit('20');
                if (Jaws_Error::IsError($result)) {
                    return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_GETTING_FILTERED_COMMENTS'), _t('COMMENTS_NAME'));
                }
            }
            break;
        }

        if (in_array($status, array('approved', 'waiting', 'spam'))) {
            $params['status'] = $status;
            $sql.= ' AND [status] = {status}';
        }

        if (is_numeric($limit)) {
            $result = $GLOBALS['db']->setLimit(10, $limit);
            if (Jaws_Error::IsError($result)) {
                return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_GETTING_FILTERED_COMMENTS'), _t('COMMENTS_NAME'));
            }
        }
        $sql.= ' ORDER BY [createtime] DESC';

        $rows = $GLOBALS['db']->queryAll($sql, $params);
        if (Jaws_Error::IsError($rows)) {
            return new Jaws_Error(_t('GLOBAL_COMMENT_ERROR_GETTING_FILTERED_COMMENTS'), _t('COMMENTS_NAME'));
        }

        return $rows;
    }

    /**
     * Does a massive comment delete
     *
     * @access  public
     * @param   array   $ids  Ids of comments
     * @return  mixed   True on Success or Jaws_Error on Failure
     */
    function MassiveCommentDelete($ids)
    {
        if (!is_array($ids)) {
            $ids = func_get_args();
        }

        foreach ($ids as $id) {
            $res = $this->DeleteComment(null, $id);
            if (Jaws_Error::IsError($res)) {
                $GLOBALS['app']->Session->PushLastResponse(_t('GLOBAL_ERROR_COMMENT_NOT_DELETED'), RESPONSE_ERROR);
                return new Jaws_Error(_t('GLOBAL_ERROR_COMMENT_NOT_DELETED'), _t('BLOG_NAME'));
            }
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('GLOBAL_COMMENT_DELETED'), RESPONSE_NOTICE);
        return true;
    }
}