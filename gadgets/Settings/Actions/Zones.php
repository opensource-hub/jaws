<?php
/**
 * Settings Admin Gadget
 *
 * @category    GadgetAdmin
 * @package     Settings
 */
class Settings_Actions_Zones extends Jaws_Gadget_Action
{
    /**
     * Get cities
     *
     * @access  public
     * @return  array   Response array (notice or error)
     */
    function GetCities()
    {
        $zone = jaws()->request->fetch(array('province', 'country'));
        if (is_null($zone['province'])) {
            $provinces = jaws()->request->fetch('province:array', 'post');
        } elseif ($zone['province'] === '') {
            return array();
        } else {
            $provinces = array($zone['province']);
        }

        $cities = $this->gadget->model->load('Zones')->GetCities($provinces, $zone['country']);
        return Jaws_Error::IsError($cities)? array() : $cities;
    }

}