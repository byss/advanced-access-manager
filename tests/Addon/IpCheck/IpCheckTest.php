<?php

/**
 * ======================================================================
 * LICENSE: This file is subject to the terms and conditions defined in *
 * file 'license.txt', which is part of this source code package.       *
 * ======================================================================
 */

namespace AAM\UnitTest\Addon\IpCheck;

use AAM,
    AAM_Service_Content,
    AAM_Core_Object_Post,
    PHPUnit\Framework\TestCase,
    AAM\UnitTest\Libs\ResetTrait,
    AAM\AddOn\IPCheck\Object\IPCheck as IPCheckObject;

/**
 * Test cases for the IP Check addon
 *
 * @author Vasyl Martyniuk <vasyl@vasyltech.com>
 * @version 6.0.0
 */
class IpCheckTest extends TestCase
{
    use ResetTrait;

    /**
     * Test that entire website is restricted when IP matched
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testEntireWebsiteRestricted()
    {
        // Override the default handlers so we can suppress die exit
        add_filter('wp_die_handler', function() {
            return function($message, $title) {
                _default_wp_die_handler($message, $title, array('exit' => false));
            };
        }, PHP_INT_MAX);

        // Fake the IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $object = AAM::getUser()->getObject(IPCheckObject::OBJECT_TYPE);
        $this->assertTrue($object->updateOptionItem('ip|127.0.0.1', true)->save());

        // Capture the WP Die message
        ob_start();
        do_action('wp');
        $content = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('Access Denied', $content);

        // Reset WP Query
        remove_all_filters('wp_die_handler', PHP_INT_MAX);
        unset($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Test that access is denied based on user IP address
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testPageRestrictedByIp()
    {
        $object = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        // Set restriction
        $this->assertTrue($object->updateOptionItem('selective', array(
            'rules' => array(
                'ip|127.0.0.1' => true,
            ),
            'enabled' => true
        ))->save());

        // Reset all internal cache
        $this->_resetSubjects();

        // Verify that access is denied by IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $post = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        $result = AAM_Service_Content::getInstance()->isAuthorizedToReadPost($post);
        $this->assertEquals('WP_Error', get_class($result));
        $this->assertEquals(
            'User is unauthorized to access this post. Access Denied.',
            $result->get_error_message()
        );

        // Reset original state
        unset($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Test that access is denied for wildcard IP address
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testPageRestrictedByIpWildcard()
    {
        $object = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        // Set restriction
        $this->assertTrue($object->updateOptionItem('selective', array(
            'rules' => array(
                'ip|127.0.0.*' => true,
            ),
            'enabled' => true
        ))->save());

        // Reset all internal cache
        $this->_resetSubjects();

        // Verify that access is denied by IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.3';

        $post = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        $result = AAM_Service_Content::getInstance()->isAuthorizedToReadPost($post);
        $this->assertEquals('WP_Error', get_class($result));
        $this->assertEquals(
            'User is unauthorized to access this post. Access Denied.',
            $result->get_error_message()
        );
    }

    /**
     * Test that access is denied for the IP range
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testPageRestrictedByIpRange()
    {
        $object = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        // Set restriction
        $this->assertTrue($object->updateOptionItem('selective', array(
            'rules' => array(
                'ip|127.0.0.0-20' => true,
            ),
            'enabled' => true
        ))->save());

        // Reset all internal cache
        $this->_resetSubjects();

        // Verify that access is denied by IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.5';

        $post = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        $result = AAM_Service_Content::getInstance()->isAuthorizedToReadPost($post);
        $this->assertEquals('WP_Error', get_class($result));
        $this->assertEquals(
            'User is unauthorized to access this post. Access Denied.',
            $result->get_error_message()
        );
    }

    /**
     * Test that access is denied by the referred host
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testPageRestrictedByHost()
    {
        $object = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        // Set restriction
        $this->assertTrue($object->updateOptionItem('selective', array(
            'rules' => array(
                'host|example.local' => true,
            ),
            'enabled' => true
        ))->save());

        // Reset all internal cache
        $this->_resetSubjects();

        // Verify that access is denied by referred host
        $_SERVER['HTTP_REFERER'] = 'https://example.local';

        $post = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        $result = AAM_Service_Content::getInstance()->isAuthorizedToReadPost($post);
        $this->assertEquals('WP_Error', get_class($result));
        $this->assertEquals(
            'User is unauthorized to access this post. Access Denied.',
            $result->get_error_message()
        );
    }

    /**
     * Test that access is denied by query param
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testPageRestrictedByRef()
    {
        $object = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        // Set restriction
        $this->assertTrue($object->updateOptionItem('selective', array(
            'rules' => array(
                'ref|test' => true,
            ),
            'enabled' => true
        ))->save());

        // Reset all internal cache
        $this->_resetSubjects();

        // Verify that access is denied by ref
        $_GET['ref'] = 'test';

        $post = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        $result = AAM_Service_Content::getInstance()->isAuthorizedToReadPost($post);
        $this->assertEquals('WP_Error', get_class($result));
        $this->assertEquals(
            'User is unauthorized to access this post. Access Denied.',
            $result->get_error_message()
        );
    }

    /**
     * Test that cookie with JWT is sent when access is granted
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testWebsiteAccessCookieSetup()
    {
        // Fake the IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $object = AAM::getUser()->getObject(IPCheckObject::OBJECT_TYPE);
        $this->assertTrue($object->updateOptionItem('ip|127.0.0.1', false)->save());

        // Capture the WP Die message
        ob_start();
        do_action('wp');
        ob_end_clean();

        $this->assertCount(1, array_filter(xdebug_get_headers(), function($m) {
            return (strpos($m, 'aam_ipcheck_jwt=') !== false);
        }));

        // Reset WP Query
        unset($_SERVER['REMOTE_ADDR']);
        header_remove('Set-Cookie');
    }

    /**
     * Test that cookie with JWT is sent when access to page is granted
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function testPageAccessCookieSetup()
    {
        $object = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        // Set restriction
        $this->assertTrue($object->updateOptionItem('selective', array(
            'rules' => array(
                'ip|127.0.0.0-20' => false,
            ),
            'enabled' => true
        ))->save());

        // Reset all internal cache
        $this->_resetSubjects();

        // Verify that access is denied by IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.5';

        $post = AAM::getUser()->getObject(
            AAM_Core_Object_Post::OBJECT_TYPE, AAM_UNITTEST_POST_ID
        );

        $this->assertTrue(
            AAM_Service_Content::getInstance()->isAuthorizedToReadPost($post)
        );

        // Note! 2 is because there is no way to clear sent headers with xdebug_*
        $this->assertCount(2, array_filter(xdebug_get_headers(), function($m) {
            return (strpos($m, 'aam_ipcheck_jwt=') !== false);
        }));

        // Reset WP Query
        unset($_SERVER['REMOTE_ADDR']);
    }

}