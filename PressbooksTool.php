<?php
/*
 *  pressbooks-lti-tool - WordPress module to integrate LTI support with Pressbooks
 *  Copyright (C) 2023  Stephen P Vickers
 *
 *  Author: stephen@spvsoftwareproducts.com
 */

use ceLTIc\LTI;
use ceLTIc\LTI\Profile;
use ceLTIc\LTI\Service;
use Pressbooks\Book;

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lti-tool' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lti-tool' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'WPTool.php');

/**
 * Override Tool object used by the LTI Tool plugin.
 */
class Pressbooks_LTI_Tool extends LTI_Tool_WPTool
{

    public function __construct($data_connector)
    {
        parent::__construct($data_connector);
        $this->product = new Profile\Item('29d05a6d-5806-452f-b054-ba60bd8e93fe', 'Pressbooks',
            'An open source book content management system.', 'https://pressbooks.org');
        $requiredMessages = array(new Profile\Message('basic-lti-launch-request', '?lti-tool',
                array('User.id', 'Membership.role', 'Person.name.full', 'Person.name.family', 'Person.name.given', 'Person.email.primary', 'Context.id')),
            new LTI\Profile\Message('ContentItemSelectionRequest', '?lti-tool',
                array('User.id', 'Membership.role', 'Person.name.full', 'Person.name.family', 'Person.name.given', 'Person.email.primary', 'Context.id')));
        $this->resourceHandlers = array(new Profile\ResourceHandler(
                new Profile\Item('pb', 'Pressbooks', 'Book content management.'), '?' . PRESSBOOKS_LTI_TOOL_PLUGIN_NAME . '&icon',
                $requiredMessages, array()));
        if (!isset($this->requiredScopes[Service\LineItem::$SCOPE])) {
            $this->requiredScopes[] = Service\LineItem::$SCOPE;
        }
        if (!isset($this->requiredScopes[Service\Score::$SCOPE])) {
            $this->requiredScopes[] = Service\Score::$SCOPE;
        }
    }

    /**
     * Handle a content-item/deep linking message.
     *
     * @global type $lti_tool_session
     */
    protected function onContentItem(): void
    {
        global $lti_tool_session;

        $options = lti_tool_get_options();
        $this->init_session();
        $user_login = $this->get_user_login();
        $user = $this->init_user($user_login);
        if ($this->ok) {
            $this->login_user(get_current_blog_id(), $user, $user_login, $options);
            $this->redirectUrl = get_option('siteurl') . '/?' . PRESSBOOKS_LTI_TOOL_PLUGIN_NAME . '&deeplink';
        }
        $lti_tool_session['is_content_item'] = true;
        $lti_tool_session['key'] = $this->platform->getKey();
        $lti_tool_session['lti_version'] = $this->platform->ltiVersion;
        $lti_tool_session['return_url'] = $this->returnUrl;
        $lti_tool_session['accept_multiple'] = (!empty($this->messageParameters['accept_multiple']) && ($this->messageParameters['accept_multiple'] === 'true')) ? true : false;

        $lti_tool_session['data'] = (isset($this->messageParameters['data'])) ? $this->messageParameters['text'] : null;

        lti_tool_set_session();
    }

    /**
     * Handle a launch message.
     *
     * Redirect users to the book section as denoted in the 'section' custom parameter (if available to the launching platform).
     *
     * @global type $lti_tool_session
     */
    protected function onLaunch(): void
    {
        global $lti_tool_session;

        $options = lti_tool_get_options();
        $this->ok = !empty($this->messageParameters['custom_section']);
        if ($this->ok) {
            $parts = explode('.', $this->messageParameters['custom_section']);
            $this->ok = count($parts) === 2;
        }
        if (!$this->ok) {
            $this->reason = 'Missing or invalid custom parameter';
        } else {
            switch_to_blog(intval($parts[0]));
            $this->ok = Book::isBook();
            if (!$this->ok) {
                $this->reason = 'Book not found';
            }
        }
        if ($this->ok) {
            $available_books = explode(',', $this->platform->getSetting('__pressbooks_available_books'));
            $this->ok = in_array($parts[0], $available_books);
            if (!$this->ok) {
                $this->reason = 'Book not available';
            }
        }
        if ($this->ok) {
            $this->init_session();
            $user_login = $this->get_user_login();
            $user = $this->init_user($user_login);
        }
        if ($this->ok) {
            $lti_tool_session['key'] = $this->resourceLink->getKey();
            $this->login_user($parts[0], $user, $user_login, $options);
            $post_id = intval($parts[1]);
            if (!empty($post_id)) {
                $url = get_permalink($post_id);
            } else {
                $url = get_home_url();
            }
            if ($url) {
                $this->redirectUrl = $url;
                $lti_tool_session['hide_nav'] = !empty($this->platform->getSetting('__pressbooks_hide_navigation'));
            }
        }

        $lti_tool_session['contextpk'] = $this->context->getRecordId();
        $lti_tool_session['userpk'] = $this->userResult->getRecordId();

        lti_tool_set_session();
    }

}
