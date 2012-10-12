<?php
/**
 * Users Core Gadget
 *
 * @category   GadgetModel
 * @package    Users
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2006-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Users_Model_Personal extends Jaws_Model
{
    /**
     * Updates user profile
     *
     * @access  public
     * @param   int      $uid       User ID
     * @param   string   $username  Username
     * @param   string   $nickname  User's display name
     * @param   string   $fname     First name
     * @param   string   $lname     Last name
     * @param   string   $gender    User gender
     * @param   string   $dob       User birth date
     * @param   string   $url       User URL
     * @return  mixed    True on success or Jaws_Error on failure
     */
    function UpdatePersonal($uid, $fname, $lname, $gender, $dob, $url)
    {
        require_once JAWS_PATH . 'include/Jaws/User.php';
        $jUser = new Jaws_User;
        $pInfo = array('fname'  => $fname,
                       'lname'  => $lname,
                       'gender' => $gender,
                       'dob'    => $dob,
                       'url'    => $url);

        $result = $jUser->UpdatePersonalInfo($uid, $pInfo);
        return $result;
    }

}