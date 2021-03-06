<?php
/**
 * AbuseReporter Gadget
 *
 * @category    Gadget
 * @package     AbuseReporter
 */
class AbuseReporter_Actions_Report extends Jaws_Gadget_Action
{
    /**
     * Report UI
     *
     * @access  public
     * @return  string  XHTML template content
     */
    function ReportUI()
    {
        $tpl = $this->gadget->template->load('Report.html');
        $tpl->SetBlock('Report');

        $post = $this->gadget->request->fetch(array('report_gadget', 'report_action', 'report_reference'), 'post');
        $tpl->SetVariable('gadget', $post['report_gadget']);
        $tpl->SetVariable('action', $post['report_action']);
        $tpl->SetVariable('reference', $post['report_reference']);

        $tpl->SetVariable('lbl_comment', _t('ABUSEREPORTER_COMMENT'));
        $tpl->SetVariable('lbl_type', _t('ABUSEREPORTER_TYPE'));
        $tpl->SetVariable('lbl_priority', _t('ABUSEREPORTER_PRIORITY'));

        // types
        $types = array(
            AbuseReporter_Info::TYPE_ABUSE_0 => _t('ABUSEREPORTER_TYPE_ABUSE_0'),
            AbuseReporter_Info::TYPE_ABUSE_1 => _t('ABUSEREPORTER_TYPE_ABUSE_1'),
            AbuseReporter_Info::TYPE_ABUSE_2 => _t('ABUSEREPORTER_TYPE_ABUSE_2'),
            AbuseReporter_Info::TYPE_ABUSE_3 => _t('ABUSEREPORTER_TYPE_ABUSE_3'),
            AbuseReporter_Info::TYPE_ABUSE_4 => _t('ABUSEREPORTER_TYPE_ABUSE_4'),
            AbuseReporter_Info::TYPE_ABUSE_5 => _t('ABUSEREPORTER_TYPE_ABUSE_5'),
        );
        foreach ($types as $type => $title) {
            $tpl->SetBlock('Report/type');
            $tpl->SetVariable('value', $type);
            $tpl->SetVariable('title', $title);
            $tpl->ParseBlock('Report/type');
        }

        // priority
        $priorities = array(
            AbuseReporter_Info::PRIORITY_VERY_HIGH => _t('ABUSEREPORTER_PRIORITY_VERY_HIGH'),
            AbuseReporter_Info::PRIORITY_HIGH => _t('ABUSEREPORTER_PRIORITY_HIGH'),
            AbuseReporter_Info::PRIORITY_NORMAL => _t('ABUSEREPORTER_PRIORITY_NORMAL'),
            AbuseReporter_Info::PRIORITY_LOW => _t('ABUSEREPORTER_PRIORITY_LOW'),
            AbuseReporter_Info::PRIORITY_VERY_LOW => _t('ABUSEREPORTER_PRIORITY_VERY_LOW'),
        );
        foreach ($priorities as $priority => $title) {
            $tpl->SetBlock('Report/priority');
            $tpl->SetVariable('value', $priority);
            $tpl->SetVariable('title', $title);
            $tpl->ParseBlock('Report/priority');
        }

        $tpl->ParseBlock('Report');
        return $tpl->Get();
    }

    /**
     * Save new report
     *
     * @access  public
     * @return  void
     */
    function SaveReport()
    {
        $post = jaws()->request->fetch(
            array('report_gadget', 'report_action', 'report_reference', 'url', 'comment', 'type', 'priority'),
            'post'
        );
        $currentUser = $GLOBALS['app']->Session->GetAttribute('user');
        $result = $this->gadget->model->load('Reports')->SaveReport(
            $currentUser,
            $post['report_gadget'],
            $post['report_action'],
            $post['report_reference'],
            $post['url'],
            $post['comment'],
            $post['type'],
            $post['priority']
        );

        $response = array(
            'gadget' => $post['report_gadget'],
            'action' => $post['report_action'],
            'reference' => $post['report_reference'],
        );

        if (Jaws_Error::isError($result)) {
            $error = $result->GetMessage();
            if ($result->GetCode() == -3) {
                $error = _t('ABUSEREPORTER_ERROR_REPORT_ALREADY_EXIST');
            }
            return $GLOBALS['app']->Session->GetResponse($error, RESPONSE_ERROR, $response);
        } else {
            return $GLOBALS['app']->Session->GetResponse(_t('ABUSEREPORTER_REPORT_SAVED'), RESPONSE_NOTICE, $response);
        }
    }

}