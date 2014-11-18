<?php
/**
 * Copyright 2011-2014 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2011-2014 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Defines an AJAX variable queue for IMP.  These are variables that may be
 * generated by various IMP code that should be added to the eventual output
 * sent to the browser.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2011-2014 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Ajax_Queue
{
    /**
     * Callback method to use for each ftree element.
     *
     * @var callback
     */
    public $ftreeCallback;

    /**
     * The list of compose autocompleter address error data.
     *
     * @var array
     */
    protected $_addr = array();

    /**
     * The list of attachments.
     *
     * @var array
     */
    protected $_atc = array();

    /**
     * The compose object.
     *
     * @var IMP_Compose
     */
    protected $_compose;

    /**
     * Flag entries to add to response.
     *
     * @var array
     */
    protected $_flag = array();

    /**
     * Add flag configuration to response.
     *
     * @var integer
     */
    protected $_flagconfig = 0;

    /**
     * Mailbox options.
     *
     * @var array
     */
    protected $_mailboxOpts = array();

    /**
     * Message queue.
     *
     * @var array
     */
    protected $_messages = array();

    /**
     * Maillog queue.
     *
     * @var array
     */
    protected $_maillog = array();

    /**
     * Poll mailboxes.
     *
     * If null, don't output polled information unless explicitly told to.
     *
     * @var array
     */
    protected $_poll = array();

    /**
     * Add quota information to response?
     *
     * Array w/2 keys: mailbox, force
     * If null, never sends quota information.
     *
     * @var mixed
     */
    protected $_quota = false;

    /**
     * Generates AJAX response task data from the queue.
     *
     * For compose autocomplete address error data (key: 'compose-addr'), an
     * array with keys as the autocomplete DOM element and the values as
     * arrays. The value arrays have keys as the autocomplete address ID, and
     * the value is a space-separated list of classnames to add.
     *
     * For compose attachment data (key: 'compose-atc'), an array of objects
     * with these properties:
     *   - icon: (string) Data url string containing icon information.
     *   - name: (string) The attachment name
     *   - num: (integer) The current attachment number
     *   - size: (string) The size of the attachment
     *   - type: (string) The MIME type of the attachment
     *   - view: (boolean) Link to attachment preivew page
     *
     * For compose cacheid data (key: 'compose'), an object with these
     * properties:
     *   - atclimit: (integer) If set, the number of further attachments
     *               that are allowed.
     *   - atcmax: (integer) The maximum size (in bytes) of an attachment.
     *   - cacheid: (string) Current cache ID of the compose message.
     *   - hmac: (string) HMAC string used to validate draft in case of
     *           session timeout.
     *
     * For flag data (key: 'flag'), an array of objects with these properties:
     *   - add: (array) The list of flags that were added.
     *   - buids: (string) Indices of the messages that have changed (IMAP
     *            sequence string; mboxes are base64url encoded).
     *   - remove: (array) The list of flags that were removed.
     *   - replace: (array) Replace the flag list with these flags.
     *
     * For flag configuration data (key: 'flag-config'), an array containing
     * flag data. All flags returned in dynamic mode; only flags labeled below
     * as [sm] are returned in smartmobile mode:
     *   - a: (boolean) Indicates a flag that can be *a*ltered.
     *   - b: (string) Background color [sm].
     *   - c: (string) CSS class.
     *   - f: (string) Foreground color [sm].
     *   - i: (string) CSS icon [sm].
     *   - id: (string) Flag ID (IMAP flag id).
     *   - l: (string) Flag label [sm].
     *   - s: (boolean) Indicates a flag that can be *s*earched for [sm].
     *   - u: (boolean) Indicates a *u*ser flag.
     *
     * For mailbox data (key: 'mailbox'), an array with these keys:
     *   - a: (array) Mailboxes that were added (base64url encoded).
     *   - all: (integer) If true, all mailboxes should be shown.
     *   - base: (string) Base mailbox (base64url encoded).
     *   - c: (array) Mailboxes that were changed (base64url encoded).
     *   - d: (array) Mailboxes that were deleted (base64url encoded).
     *   - expand: (integer) Expand subfolders on load.
     *   - switch: (string) Load this mailbox (base64url encoded).
     *
     * For maillog data (key: 'maillog'), an object with these properties:
     *   - buid: (integer) BUID.
     *   - log: (array) List of log entries.
     *   - mbox: (string) Mailbox.
     *
     * For message preview data (key: 'message'), an object with these
     * properties:
     *   - buid: (integer) BUID.
     *   - data: (object) Message viewport data.
     *   - mbox: (string) Mailbox.
     *
     * For poll data (key: 'poll'), an array with keys as base64url encoded
     * mailbox names, values as the number of unseen messages.
     *
     * For quota data (key: 'quota'), an array with these keys:
     *   - m: (string) Quota message.
     *   - p: (integer) Quota percentage.
     *
     * @param IMP_Ajax_Application $ajax  The AJAX object.
     */
    public function add(IMP_Ajax_Application $ajax)
    {
        global $injector;

        /* Add autocomplete address error information. */
        if (!empty($this->_addr)) {
            $ajax->addTask('compose-addr', $this->_addr);
            $this->_addr = array();
        }

        /* Add compose attachment information. */
        if (!empty($this->_atc)) {
            $ajax->addTask('compose-atc', $this->_atc);
            $this->_atc = array();
        }

        /* Add compose information. */
        if (!is_null($this->_compose)) {
            $compose = new stdClass;
            if (($addl = $this->_compose->additionalAttachmentsAllowed()) !== true) {
                $compose->atclimit = $addl;
            }
            $compose->atcmax = $this->_compose->maxAttachmentSize();
            $compose->cacheid = $this->_compose->getCacheId();
            $compose->hmac = $this->_compose->getHmac();

            $ajax->addTask('compose', $compose);
            $this->_compose = null;
        }

        /* Add flag information. */
        if (!empty($this->_flag)) {
            $ajax->addTask('flag', array_unique($this->_flag, SORT_REGULAR));
            $this->_flag = array();
        }

        /* Add flag configuration. */
        switch ($this->_flagconfig) {
        case Horde_Registry::VIEW_DYNAMIC:
        case Horde_Registry::VIEW_MINIMAL:
        case Horde_Registry::VIEW_SMARTMOBILE:
            $flags = array();
            foreach ($injector->getInstance('IMP_Flags')->getList() as $val) {
                $tmp = array(
                    'b' => $val->bgdefault ? null : $val->bgcolor,
                    'f' => $val->fgcolor,
                    'id' => $val->id,
                    'l' => $val->label,
                    's' => intval($val instanceof IMP_Flag_Imap)
                );

                if ($this->_flagconfig === Horde_Registry::VIEW_DYNAMIC) {
                    $tmp += array(
                        'a' => $val->canset,
                        'c' => $val->css,
                        'i' => $val->css ? null : $val->cssicon,
                        'u' => intval($val instanceof IMP_Flag_User)
                    );
                }

                $flags[] = array_filter($tmp);
            }
            $ajax->addTask('flag-config', $flags);
            break;
        }

        /* Add folder tree information. */
        $this->_addFtreeInfo($ajax);

        /* Add maillog information. */
        $this->_addMaillogInfo($ajax);

        /* Add message information. */
        if (!empty($this->_messages)) {
            $ajax->addTask('message', $this->_messages);
            $this->_messages = array();
        }

        /* Add poll information. */
        $poll = $poll_list = array();
        if (!empty($this->_poll)) {
            foreach ($this->_poll as $val) {
                $poll_list[strval($val)] = 1;
            }
        }

        if (count($poll_list)) {
            $imap_ob = $injector->getInstance('IMP_Factory_Imap')->create();
            if ($imap_ob->init) {
                try {
                    foreach ($imap_ob->status(array_keys($poll_list), Horde_Imap_Client::STATUS_UNSEEN) as $key => $val) {
                        $poll[IMP_Mailbox::formTo($key)] = intval($val['unseen']);
                    }
                } catch (Exception $e) {
                    // Ignore errors in status() calls.
                }
            }

            if (!empty($poll)) {
                $ajax->addTask('poll', $poll);
                $this->_poll = array();
            }
        }

        /* Add quota information. */
        if ($this->_quota &&
            ($quotadata = $injector->getInstance('IMP_Quota_Ui')->quota($this->_quota[0], $this->_quota[1]))) {
            $ajax->addTask('quota', array(
                'm' => $quotadata['message'],
                'p' => round($quotadata['percent']),
                'l' => $quotadata['percent'] >= 90
                    ? 'alert'
                    : ($quotadata['percent'] >= 75 ? 'warn' : '')
            ));
            $this->_quota = false;
        }
    }

    /**
     * Return information about the current attachment(s) for a message.
     *
     * @param mixed $ob      If an IMP_Compose object, return info on all
     *                       attachments. If an IMP_Compose_Attachment object,
     *                       only return information on that object.
     * @param integer $type  The compose type.
     */
    public function attachment($ob, $type = IMP_Compose::COMPOSE)
    {
        global $injector;

        $parts = ($ob instanceof IMP_Compose)
            ? iterator_to_array($ob)
            : array($ob);
        $viewer = $injector->getInstance('IMP_Factory_MimeViewer');

        foreach ($parts as $val) {
            $mime = $val->getPart();
            $mtype = $mime->getType();

            $tmp = array(
                'icon' => strval(Horde_Url_Data::create('image/png', file_get_contents($viewer->getIcon($mtype)->fs))),
                'name' => $mime->getName(true),
                'num' => $val->id,
                'type' => $mtype,
                'size' => IMP::sizeFormat($mime->getBytes())
            );

            if ($viewer->create($mime)->canRender('full')) {
                $tmp['url'] = strval($val->viewUrl()->setRaw(true));
                $tmp['view'] = intval(!in_array($type, array(IMP_Compose::FORWARD_ATTACH, IMP_Compose::FORWARD_BOTH)) && ($mtype != 'application/octet-stream'));
            }

            $this->_atc[] = $tmp;
        }
    }

    /**
     * Add compose data to the output.
     *
     * @param IMP_Compose $ob  The compose object.
     */
    public function compose(IMP_Compose $ob)
    {
        $this->_compose = $ob;
    }

    /**
     * Add address autocomplete error info.
     *
     * @param string $domid   The autocomplete DOM ID.
     * @param string $itemid  The autocomplete address ID.
     * @param string $class   The classname to add to the address entry.
     */
    public function compose_addr($domid, $itemid, $class)
    {
        $this->_addr[$domid][$itemid] = $class;
    }

    /**
     * Add flag entry to response queue.
     *
     * @param array $flags          List of flags that have changed.
     * @param boolean $add          Were the flags added?
     * @param IMP_Indices $indices  Indices object.
     */
    public function flag($flags, $add, IMP_Indices $indices)
    {
        global $injector;

        if ($indices instanceof IMP_Indices_Mailbox) {
            if (!count($indices) || !$indices->mailbox->access_flags) {
                return;
            }
            $indices = clone $indices;
            $indices->add($indices->buids);
        }

        $changed = $injector->getInstance('IMP_Flags')->changed($flags, $add);

        $result = new stdClass;
        if (!empty($changed['add'])) {
            $result->add = array_map('strval', $changed['add']);
        }
        if (!empty($changed['remove'])) {
            $result->remove = array_map('strval', $changed['remove']);
        }

        $result->buids = $indices->toArray();
        $this->_flag[] = $result;
    }

    /**
     * Sends replacement flag information for the indices provided.
     *
     * @param IMP_Indices $indices  Indices object.
     */
    public function flagReplace(IMP_Indices $indices)
    {
        global $injector, $prefs;

        $imp_flags = $injector->getInstance('IMP_Flags');

        foreach ($indices as $ob) {
            $list_ob = $ob->mbox->list_ob;
            $msgnum = array();

            foreach ($ob->uids as $uid) {
                $msgnum[] = $list_ob->getArrayIndex($uid) + 1;
            }

            $marray = $list_ob->getMailboxArray($msgnum, array(
                'headers' => true,
                'type' => $prefs->getValue('atc_flag')
            ));

            foreach ($marray['overview'] as $val) {
                $result = new stdClass;
                $result->buids = $ob->mbox->toBuids(new IMP_Indices($ob->mbox, $val['uid']))->toArray();
                $result->replace = array_map('strval', $imp_flags->parse(array(
                    'flags' => $val['flags'],
                    'headers' => $val['headers'],
                    'runhook' => $val,
                    'personal' => $val['envelope']->to
                )));
                $this->_flag[] = $result;
            }
        }
    }

    /**
     * Add flag configuration information to response queue.
     *
     * @param integer $view  The current view.
     */
    public function flagConfig($view)
    {
        $this->_flagconfig = $view;
    }

    /**
     * Add message data to output.
     *
     * @param IMP_Indices $indices  Index of the message.
     * @param boolean $preview      Preview data?
     * @param boolean $peek         Don't set seen flag?
     */
    public function message(IMP_Indices $indices, $preview = false,
                            $peek = false)
    {
        try {
            $show_msg = new IMP_Ajax_Application_ShowMessage($indices, $peek);
            $msg = (object)$show_msg->showMessage(array(
                'preview' => $preview
            ));
            $msg->save_as = strval($msg->save_as);

            if ($indices instanceof IMP_Indices_Mailbox) {
                $indices = $indices->buids;
            }

            foreach ($indices as $val) {
                foreach ($val->uids as $val2) {
                    $ob = new stdClass;
                    $ob->buid = $val2;
                    $ob->data = $msg;
                    $ob->mbox = $val->mbox->form_to;
                    $this->_messages[] = $ob;
                }
            }
        } catch (Exception $e) {}
    }

    /**
     * Add maillog data to output.
     *
     * @param IMP_Indices $indices  Indices object.
     */
    public function maillog(IMP_Indices $indices)
    {
        $this->_maillog[] = $indices;
    }

    /**
     * Add additional options to the mailbox output.
     *
     * @param array $name   Option name.
     * @param mixed $value  Option value.
     */
    public function setMailboxOpt($name, $value)
    {
        $this->_mailboxOpts[$name] = $value;
    }

    /**
     * Add poll entry to response queue.
     *
     * @param mixed $mboxes      A mailbox name or list of mailbox names.
     * @param boolean $explicit  If true, explicitly output poll information.
     *                           Otherwise, add only if not disabled.
     */
    public function poll($mboxes, $explicit = false)
    {
        if (is_null($this->_poll)) {
            if (!$explicit) {
                return;
            }
            $this->_poll = array();
        } elseif (empty($this->_poll) && is_null($mboxes)) {
            $this->_poll = null;
            return;
        }

        if (!is_array($mboxes)) {
            $mboxes = array($mboxes);
        }

        foreach (IMP_Mailbox::get($mboxes) as $val) {
            if ($val->polled) {
                $this->_poll[] = $val;
            }
        }
    }

    /**
     * Add quota entry to response queue.
     *
     * @param string|null $mailbox  Mailbox to query for quota. If null,
     *                              disables quota output.
     * @param boolean $force        If true, force output. If false, output
     *                              based on configured quota interval.
     */
    public function quota($mailbox, $force = true)
    {
        if (!is_null($this->_quota)) {
            if (is_null($mailbox)) {
                /* Disable quota output entirely. */
                $this->_quota = null;
            } elseif (!is_array($this->_quota) || !$this->_quota[1]) {
                /* Don't change a previously issued force quota request. */
                $this->_quota = array($mailbox, $force);
            }
        }
    }

    /**
     * Add folder tree information.
     *
     * @param IMP_Ajax_Application $ajax  The AJAX object.
     */
    protected function _addFtreeInfo(IMP_Ajax_Application $ajax)
    {
        global $injector;

        $eltdiff = $injector->getInstance('IMP_Ftree')->eltdiff;
        $out = $poll = array();

        if (!$eltdiff->track) {
            return;
        }

        if (($add = $eltdiff->add) &&
            ($elts = array_values(array_filter(array_map(array($this, '_ftreeElt'), $add))))) {
            $out['a'] = $elts;
            $poll = $add;
        }

        if (($change = $eltdiff->change) &&
            ($elts = array_values(array_filter(array_map(array($this, '_ftreeElt'), $change))))) {
            $out['c'] = $elts;
            $poll = array_merge($poll, $change);
        }

        if ($delete = $eltdiff->delete) {
            $out['d'] = IMP_Mailbox::formTo($delete);
        }

        if (!empty($out)) {
            $eltdiff->clear();
            $ajax->addTask('mailbox', array_merge($out, $this->_mailboxOpts));
            $this->poll($poll);
        }
    }

    /**
     * Create a folder tree element.
     *
     * @param string $id  Tree element ID.
     *
     * @return mixed  The element object, or null if the element is not
     *                active. Object contains the following properties:
     *   - ch: (boolean) [children] Does the mailbox contain children?
     *         DEFAULT: no
     *   - cl: (string) [class] The CSS class.
     *         DEFAULT: 'folderImg'
     *   - co: (boolean) [container] Is this mailbox a container element?
     *         DEFAULT: no
     *   - fs: (boolean) [boolean] Fixed element for sorting purposes.
     *         DEFAULT: no
     *   - i: (string) [icon] A user defined icon to use.
     *        DEFAULT: none
     *   - l: (string) [label] The mailbox display label.
     *   - m: (string) [mbox] The mailbox value (base64url encoded).
     *   - n: (boolean) [non-imap] A non-IMAP element?
     *        DEFAULT: no
     *   - nc: (boolean) [no children] Does the element not allow children?
     *         DEFAULT: no
     *   - ns: (boolean) [no sort] Don't sort on browser.
     *         DEFAULT: no
     *   - pa: (string) [parent] The parent element.
     *         DEFAULT: DimpCore.conf.base_mbox
     *   - po: (boolean) [polled] Is the element polled?
     *         DEFAULT: no
     *   - r: (integer) [remote] Is this a "remote" element? 1 is the remote
     *        container, 2 is a remote account, and 3 is a remote mailbox.
     *        DEFAULT: 0
     *   - s: (boolean) [special] Is this a "special" element?
     *        DEFAULT: no
     *   - t: (string) [title] Mailbox title.
     *        DEFAULT: 'l' val
     *   - un: (boolean) [unsubscribed] Is this mailbox unsubscribed?
     *         DEFAULT: no
     *   - v: (integer) [virtual] Virtual folder? 0 = not vfolder, 1 = system
     *        vfolder, 2 = user vfolder
     *        DEFAULT: 0
     */
    protected function _ftreeElt($id)
    {
        global $injector;

        $ftree = $injector->getInstance('IMP_Ftree');
        if (!($elt = $ftree[$id]) || $elt->base_elt) {
            return null;
        }

        $mbox_ob = $elt->mbox_ob;

        $ob = new stdClass;
        $ob->m = $mbox_ob->form_to;

        if ($elt->children) {
            $ob->ch = 1;
        } elseif ($elt->nochildren) {
            $ob->nc = 1;
        }

        $ob->l = htmlspecialchars($mbox_ob->abbrev_label);
        $label = $mbox_ob->label;
        if ($ob->l != $label) {
            $ob->t = $label;
        }

        $parent = $elt->parent;
        if (!$parent->base_elt) {
            $ob->pa = $parent->mbox_ob->form_to;
            if ($parent->remote &&
                (strcasecmp($mbox_ob->imap_mbox, 'INBOX') === 0)) {
                $ob->fs = 1;
            }
        }

        if ($elt->vfolder) {
            $ob->v = $mbox_ob->editvfolder ? 2 : 1;
            $ob->ns = 1;
        }

        if ($elt->nonimap) {
            $ob->n = 1;
            if ($mbox_ob->remote_container) {
                $ob->r = 1;
            }
        }

        if ($elt->container) {
            if (empty($ob->ch)) {
                return null;
            }
            $ob->co = 1;
        } else {
            if (!$elt->subscribed) {
                $ob->un = 1;
            }

            if (isset($ob->n) && isset($ob->r)) {
                $ob->r = ($mbox_ob->remote_account->imp_imap->init ? 3 : 2);
            }

            if ($elt->polled) {
                $ob->po = 1;
            }

            if ($elt->inbox) {
                $ob->ns = $ob->s = 1;
            } elseif ($mbox_ob->special) {
                $ob->ns = $ob->s = 1;
                $ob->t = strval($elt);
            }
        }

        $icon = $mbox_ob->icon;
        if ($icon->user_icon) {
            $ob->cl = 'customimg';
            $ob->i = strval($icon->icon);
        } elseif (!in_array($icon->class, array('folderImg', 'folderopenImg'))) {
            $ob->cl = $icon->class;
        }

        if ($this->ftreeCallback) {
            call_user_func($this->ftreeCallback, $id, $ob);
        }

        return $ob;
    }

    /**
     * Add maillog information.
     *
     * @param IMP_Ajax_Application $ajax  The AJAX object.
     */
    protected function _addMaillogInfo(IMP_Ajax_Application $ajax)
    {
        global $injector;

        if (empty($this->_maillog)) {
            return;
        }

        $imp_maillog = $injector->getInstance('IMP_Maillog');
        $maillog = array();

        foreach ($this->_maillog as $val) {
            /* Need to grab the maillog data from the "real" ID. Then check to
             * see if a different BUID exists, since the message log may need
             * to be updated in two locations at once (i.e. search mailbox and
             * real mailbox). */
            foreach ($val as $v) {
                foreach ($v->uids as $v2) {
                    $msg = new IMP_Maillog_Message(
                        new IMP_Indices($v->mbox, $v2)
                    );

                    $l = $imp_maillog->getLog($msg, array(
                        'forward',
                        'redirect',
                        'reply_all',
                        'reply_list',
                        'reply'
                    ));
                    $tmp = array();

                    foreach ($l as $v3) {
                        $tmp[] = array(
                            'm' => $v3->message,
                            't' => $v3->action
                        );
                    }

                    if ($tmp) {
                        $indices = $msg->indices;
                        if ($val instanceof IMP_Indices_Mailbox) {
                            $indices->add($val->mailbox->toBuids($indices));
                        }

                        foreach ($indices as $v4) {
                            foreach ($v4->uids as $v5) {
                                $log_ob = new stdClass;
                                $log_ob->buid = intval($v5);
                                $log_ob->log = $tmp;
                                $log_ob->mbox = $v4->mbox->form_to;
                                $maillog[] = $log_ob;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($maillog)) {
            $ajax->addTask('maillog', $maillog);
        }
    }

}
