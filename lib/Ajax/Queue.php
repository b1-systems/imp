<?php
/**
 * Defines an AJAX variable queue for IMP.  These are variables that may be
 * generated by various IMP code that should be added to the eventual output
 * to the browser.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  IMP
 */
class IMP_Ajax_Queue
{
    /**
     * Flag entries to add to response.
     *
     * @var array
     */
    protected $_flag = array();

    /**
     * Poll mailboxes.
     *
     * @var array
     */
    protected $_poll = array();

    /**
     * Add quota information to response?
     *
     * @var boolean
     */
    protected $_quota = false;

    /**
     * Generates variable data from the queue.
     *
     * @return array  Queue data.
     * <pre>
     * For flag data (key: 'flag'), an array of objects with these properties:
     *   - add: (array) The list of flags that were added.
     *   - remove: (array) The list of flags that were removed.
     *   - uids: (string) Indices of the messages that have changed (IMAP
     *           sequence string).
     *
     * For poll data (key: 'poll'), an array with keys as mailbox names,
     * values as the number of unseen messages.
     *
     * For quota data (key: 'quota'), an array with these keys:
     *   - m: (string) Quota message.
     *   - p: (integer) Quota percentage.
     * </pre>
     */
    public function generate()
    {
        $res = array();

        /* Add flag information. */
        if (!empty($this->_flag)) {
            $res['flag'] = $this->_flag;
            $this->_flag = array();
        }

        /* Add poll information. */
        $poll = $poll_list = array();
        foreach ($this->_poll as $val) {
            $poll_list[strval($val)] = 1;
        }

        $imap_ob = $GLOBALS['injector']->getInstance('IMP_Factory_Imap')->create();
        if ($imap_ob->ob) {
            foreach ($imap_ob->statusMultiple(array_keys($poll_list), Horde_Imap_Client::STATUS_UNSEEN) as $key => $val) {
                $poll[$key] = intval($val['unseen']);
            }
        }

        if (!empty($poll)) {
            $res['poll'] = $poll;
            $this->_poll = array();
        }

        /* Add quota information. */
        if ($this->_quota &&
            $GLOBALS['session']->get('imp', 'imap_quota') &&
            ($quotadata = IMP::quotaData(false))) {
            $res['quota'] = array(
                'm' => $quotadata['message'],
                'p' => round($quotadata['percent'])
            );
            $this->_quota = false;
        }

        return $res;
    }

    /**
     * Add flag entry to response queue.
     *
     * @param array $flags          List of flags that have changed.
     * @param boolean $add          Were the flags added?
     * @param IMP_Indices $indices  Indices object.
     */
    public function flag($flags, $add, $indices)
    {
        global $injector;

        if (!$injector->getInstance('IMP_Factory_Imap')->create()->access(IMP_Imap::ACCESS_FLAGS)) {
            return;
        }

        $changed = $injector->getInstance('IMP_Flags')->changed($flags, $add);

        $result = new stdClass;
        if (!empty($changed['add'])) {
            $result->add = array_map('strval', $changed['add']);
        }
        if (!empty($changed['remove'])) {
            $result->remove = array_map('strval', $changed['remove']);
        }
        $result->uids = strval($indices);

        $this->_flag[] = $result;
    }

    /**
     * Add poll entry to response queue.
     *
     * @param mixed $mboxes  A mailbox name or list of mailbox names.
     */
    public function poll($mboxes)
    {
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
     */
    public function quota()
    {
        $this->_quota = true;
    }

}
