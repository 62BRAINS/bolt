<?php
namespace Bolt\Tests\Controller\Backend;

use Bolt\Content;
use Bolt\Controller\Backend\Records;
use Bolt\Tests\Controller\ControllerUnitTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class to test correct operation of src/Controller/Backend/Records.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 **/
class RecordsTest extends ControllerUnitTest
{
    public function testDelete()
    {
        $this->setRequest(Request::create('/bolt/deletecontent/pages/4'));
        $response = $this->controller()->actionDelete($this->getRequest(), 'pages', 4);

        // This one should fail for permissions
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('error');
        $this->assertRegExp('/denied/', $err[0]);

        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $this->setService('users', $users);

        // This one should get killed by the anti CSRF check
        $response = $this->controller()->actionDelete($this->getRequest(), 'pages', 4);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('info');
        $this->assertRegExp('/could not be deleted/', $err[0]);

        $authentication = $this->getMock('Bolt\AccessControl\Authentication', array('checkAntiCSRFToken'), array($this->getApp()));
        $authentication->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));
        $this->setService('authentication', $authentication);

        $response = $this->controller()->actionDelete($this->getRequest(), 'pages', 4);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('info');
        $this->assertRegExp('/has been deleted/', $err[0]);
    }

    public function testEditGet()
    {
        // First test will fail permission so we check we are kicked back to the dashboard
        $this->setRequest(Request::create('/bolt/editcontent/pages/4'));
        $response = $this->controller()->actionEdit($this->getRequest(), 'pages', 4);
        $this->assertEquals('/bolt', $response->getTargetUrl());

        // Since we're the test user we won't automatically have permission to edit.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/editcontent/pages/4'));
        $response = $this->controller()->actionEdit($this->getRequest(), 'pages', 4);
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['contenttype']['name']);
        $this->assertInstanceOf('Bolt\Content', $context['context']['content']);

        // Test creation
        $this->setRequest(Request::create('/bolt/editcontent/pages'));
        $response = $this->controller()->actionEdit($this->getRequest(), 'pages', null);
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['contenttype']['name']);
        $this->assertInstanceOf('Bolt\Content', $context['context']['content']);
        $this->assertNull($context['context']['content']->id);

        // Test that non-existent throws a redirect
        $this->setRequest(Request::create('/bolt/editcontent/pages/310'));
        $response = $this->controller()->actionEdit($this->getRequest(), 'pages', 310);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testEditDuplicate()
    {
        // Since we're the test user we won't automatically have permission to edit.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/editcontent/pages/4', 'GET', array('duplicate' => true)));
        $original = $this->getService('storage')->getContent('pages/4');
        $response = $this->controller()->actionEdit($this->getRequest(), 'pages', 4);
        $context = $response->getContext();

        // Check that correct fields are equal in new object
        $new = $context['context']['content'];
        $this->assertEquals($new['body'], $original['body']);
        $this->assertEquals($new['title'], $original['title']);
        $this->assertEquals($new['teaser'], $original['teaser']);

        // Check that some have been cleared.
        $this->assertEquals('', $new['id']);
        $this->assertEquals('', $new['slug']);
        $this->assertEquals('', $new['ownerid']);
    }

    public function testEditCSRF()
    {
        $users = $this->getMock('Bolt\Users', array('isAllowed', 'checkAntiCSRFToken'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $users->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(false));

        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/editcontent/showcases/3', 'POST'));
        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\HttpException', 'Something went wrong');
        $this->controller()->actionEdit($this->getRequest(), 'showcases', 3);
    }

    public function testEditPermissions()
    {
        $authentication = $this->getMock('Bolt\AccessControl\Authentication', array('checkAntiCSRFToken'), array($this->getApp()));
        $authentication->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));
        $this->setService('authentication', $authentication);

        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->at(0))
            ->method('isAllowed')
            ->will($this->returnValue(false));
        $this->setService('users', $users);

        // We should get kicked here because we dont have permissions to edit this
        $this->setRequest(Request::create('/bolt/editcontent/showcases/3', 'POST'));
        $response = $this->controller()->actionEdit($this->getRequest(), 'showcases', 3);
        $this->assertEquals('/bolt', $response->getTargetUrl());
    }

    public function testEditPost()
    {
        $authentication = $this->getMock('Bolt\AccessControl\Authentication', array('checkAntiCSRFToken'), array($this->getApp()));
        $authentication->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));
        $this->setService('authentication', $authentication);

        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/editcontent/showcases/3', 'POST', array('floatfield' => 1.2)));
        //$original = $this->getService('storage')->getContent('showcases/3');
        $response = $this->controller()->actionEdit($this->getRequest(), 'showcases', 3);
        $this->assertEquals('/bolt/overview/showcases', $response->getTargetUrl());
    }

    public function testEditPostAjax()
    {
        $authentication = $this->getMock('Bolt\AccessControl\Authentication', array('checkAntiCSRFToken'), array($this->getApp()));
        $authentication->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));
        $this->setService('authentication', $authentication);

        // Since we're the test user we won't automatically have permission to edit.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/editcontent/pages/4?returnto=ajax', 'POST'));
        $original = $this->getService('storage')->getContent('pages/4');
        $response = $this->controller()->actionEdit($this->getRequest(), 'pages', 4);
        $returned = json_decode($response->getContent());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $this->assertEquals($original['title'], $returned->title);
    }

    public function testModify()
    {
        // Try status switches
        $this->setRequest(Request::create('/bolt/content/held/pages/3'));

        // This one should fail for lack of permission
        $response = $this->controller()->actionModify($this->getRequest(), 'held', 'pages', 3);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('error');
        $this->assertRegExp('/right privileges/', $err[0]);

        $users = $this->getMock('Bolt\Users', array('isAllowed', 'checkAntiCSRFToken', 'isContentStatusTransitionAllowed'), array($this->getApp()));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $this->setService('users', $users);

        // This one should fail for the second permission check `isContentStatusTransitionAllowed`
        $response = $this->controller()->actionModify($this->getRequest(), 'held', 'pages', 3);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('error');
        $this->assertRegExp('/right privileges/', $err[0]);

        $this->getService('users')->expects($this->any())
            ->method('isContentStatusTransitionAllowed')
            ->will($this->returnValue(true));

        $response = $this->controller()->actionModify($this->getRequest(), 'held', 'pages', 3);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('info');
        $this->assertRegExp('/has been changed/', $err[0]);

        // Test an invalid action fails
        $this->setRequest(Request::create('/bolt/content/fake/pages/3'));
        $response = $this->controller()->actionModify($this->getRequest(), 'fake', 'pages', 3);
        $err = $this->getService('session')->getFlashBag()->get('error');
        $this->assertRegExp('/No such action/', $err[0]);

        // Test that any save error gets reported
        $this->setRequest(Request::create('/bolt/content/held/pages/3'));

        $storage = $this->getMock('Bolt\Storage', array('updateSingleValue'), array($this->getApp()));
        $storage->expects($this->once())
            ->method('updateSingleValue')
            ->will($this->returnValue(false));

        $this->setService('storage', $storage);

        $response = $this->controller()->actionModify($this->getRequest(), 'held', 'pages', 3);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('info');
        $this->assertRegExp('/could not be modified/', $err[0]);

        // Test the delete proxy action
        // Note that the response will be 'could not be deleted'. Since this just
        // passes on the the deleteContent method that is enough to indicate that
        // the work of this method is done.
        $this->setRequest(Request::create('/bolt/content/delete/pages/3'));
        $response = $this->controller()->actionModify($this->getRequest(), 'delete', 'pages', 3);
        $this->assertEquals('/bolt/overview/pages', $response->getTargetUrl());
        $err = $this->getService('session')->getFlashBag()->get('info');
        $this->assertRegExp('/could not be deleted/', $err[0]);
    }

    public function testOverview()
    {
        $this->setRequest(Request::create('/bolt/overview/pages'));
        $response = $this->controller()->actionOverview($this->getRequest(), 'pages');
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['contenttype']['name']);
        $this->assertGreaterThan(1, count($context['context']['multiplecontent']));

        // Test the the default records per page can be set
        $this->setRequest(Request::create('/bolt/overview/showcases'));
        $response = $this->controller()->actionOverview($this->getRequest(), 'showcases');

        // Test redirect when user isn't allowed.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->once())
            ->method('isAllowed')
            ->will($this->returnValue(false));
        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/overview/pages'));
        $response = $this->controller()->actionOverview($this->getRequest(), 'pages');
        $this->assertEquals('/bolt', $response->getTargetUrl());
    }

    public function testOverviewFiltering()
    {
        $this->setRequest(Request::create(
            '/bolt/overview/pages',
            'GET',
            array(
                'filter'            => 'Lorem',
                'taxonomy-chapters' => 'main'
            )
        ));
        $response = $this->controller()->actionOverview($this->getRequest(), 'pages');
        $context = $response->getContext();
        $this->assertArrayHasKey('filter', $context['context']);
        $this->assertEquals('Lorem', $context['context']['filter'][0]);
        $this->assertEquals('main', $context['context']['filter'][1]);
    }

    public function testRelated()
    {
        $this->setRequest(Request::create('/bolt/relatedto/showcases/1'));
        $response = $this->controller()->actionRelated($this->getRequest(), 'showcases', 1);
        $context = $response->getContext();
        $this->assertEquals(1, $context['context']['id']);
        $this->assertEquals('Showcase', $context['context']['name']);
        $this->assertEquals('Showcases', $context['context']['contenttype']['name']);
        $this->assertEquals(2, count($context['context']['relations']));
        // By default we show the first one
        $this->assertEquals('Entries', $context['context']['show_contenttype']['name']);

        // Now we specify we want to see pages
        $this->setRequest(Request::create('/bolt/relatedto/showcases/1', 'GET', array('show' => 'pages')));
        $response = $this->controller()->actionRelated($this->getRequest(), 'showcases', 1);
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['show_contenttype']['name']);

        // Try a request where there are no relations
        $this->setRequest(Request::create('/bolt/relatedto/pages/1'));
        $response = $this->controller()->actionRelated($this->getRequest(), 'pages', 1);
        $context = $response->getContext();
        $this->assertNull($context['context']['relations']);

        // Test redirect when user isn't allowed.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($this->getApp()));
        $users->expects($this->once())
            ->method('isAllowed')
            ->will($this->returnValue(false));
        $this->setService('users', $users);

        $this->setRequest(Request::create('/bolt/relatedto/showcases/1'));
        $response = $this->controller()->actionRelated($this->getRequest(), 'showcases', 1);
        $this->assertEquals('/bolt', $response->getTargetUrl());
    }

    /**
     * @return \Bolt\Controller\Backend\Records
     */
    protected function controller()
    {
        return $this->getService('controller.backend.records');
    }
}
