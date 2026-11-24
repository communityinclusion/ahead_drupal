<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Tests scheduling nodes through JSON:API.
 *
 * @group scheduler
 */
class SchedulerJsonApiTest extends SchedulerBrowserTestBase {

  use JsonApiRequestTestTrait;

  /**
   * Additional modules required for JSON:API requests.
   *
   * @var array
   */
  protected static $modules = ['jsonapi', 'serialization', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Allow nodes to be created and updated through JSON:API.
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();
    // Build the JSON:API routes for the content types created during set-up.
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that the scheduling permission is required to set the dates.
   */
  public function testJsonApiPermission() {
    $publish_time = strtotime('+ 6 hours', $this->requestTime);
    $unpublish_time = strtotime('+ 10 hours', $this->requestTime);
    $publish_date = $this->dateFormatter->format($publish_time, 'custom', \DateTime::RFC3339);
    $unpublish_date = $this->dateFormatter->format($unpublish_time, 'custom', \DateTime::RFC3339);

    // Check that a user with the scheduling permission can set the dates when
    // creating a node.
    $title = 'Scheduled - ' . $this->randomMachineName(10);
    $this->jsonApiRequest('POST', NULL, [
      'title' => $title,
      'publish_on' => $publish_date,
      'unpublish_on' => $unpublish_date,
    ], 201);
    $node = $this->drupalGetNodeByTitle($title, TRUE);
    $this->assertEquals($publish_time, $node->publish_on->value, 'The publish_on date is set.');
    $this->assertEquals($unpublish_time, $node->unpublish_on->value, 'The unpublish_on date is set.');

    // Check that the user can change the dates when updating the node.
    $this->jsonApiRequest('PATCH', $node->uuid(), [
      'publish_on' => $this->dateFormatter->format($publish_time + 3600, 'custom', \DateTime::RFC3339),
    ], 200);
    $this->nodeStorage->resetCache([$node->id()]);
    $this->assertEquals($publish_time + 3600, $this->nodeStorage->load($node->id())->publish_on->value, 'The publish_on date is changed.');

    // Remove the scheduling permission from the user.
    foreach ($this->schedulerUser->getRoles(TRUE) as $rid) {
      user_role_revoke_permissions($rid, ['schedule publishing of nodes']);
    }

    // Check that the user can no longer set the dates, but can still create and
    // update nodes.
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
      'publish_on' => $publish_date,
    ], 403);
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
      'unpublish_on' => $unpublish_date,
    ], 403);
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
    ], 201);
    $this->jsonApiRequest('PATCH', $node->uuid(), [
      'publish_on' => $publish_date,
    ], 403);
    $this->jsonApiRequest('PATCH', $node->uuid(), [
      'title' => $this->randomMachineName(10),
    ], 200);
  }

  /**
   * Tests that the dates can only be set when enabled for the content type.
   */
  public function testJsonApiNonEnabledType() {
    $publish_date = $this->dateFormatter->format(strtotime('+ 6 hours', $this->requestTime), 'custom', \DateTime::RFC3339);
    $unpublish_date = $this->dateFormatter->format(strtotime('+ 10 hours', $this->requestTime), 'custom', \DateTime::RFC3339);

    // Check that the publish_on date cannot be set when publishing is not
    // enabled.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_enable', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
      'publish_on' => $publish_date,
    ], 403);
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
      'unpublish_on' => $unpublish_date,
    ], 201);

    // Check that the unpublish_on date cannot be set when unpublishing is not
    // enabled.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)
      ->save();
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
      'publish_on' => $publish_date,
    ], 201);
    $this->jsonApiRequest('POST', NULL, [
      'title' => $this->randomMachineName(10),
      'unpublish_on' => $unpublish_date,
    ], 403);
  }

  /**
   * Sends a JSON:API request as the scheduler user and checks the response.
   *
   * @param string $method
   *   The HTTP method, 'POST' to create a node or 'PATCH' to update one.
   * @param string|null $uuid
   *   The UUID of the node to update, or NULL when creating a node.
   * @param array $attributes
   *   The node field values, keyed by field name.
   * @param int $expected_status
   *   The expected HTTP status code.
   */
  protected function jsonApiRequest($method, $uuid, array $attributes, $expected_status) {
    $resource_type = 'node--' . $this->type;
    $data = ['type' => $resource_type, 'attributes' => $attributes];
    if ($uuid) {
      $data['id'] = $uuid;
      $url = Url::fromRoute("jsonapi.$resource_type.individual", ['entity' => $uuid]);
    }
    else {
      $url = Url::fromRoute("jsonapi.$resource_type.collection.post");
    }
    $response = $this->request($method, $url, [
      RequestOptions::HEADERS => [
        'Accept' => 'application/vnd.api+json',
        'Content-Type' => 'application/vnd.api+json',
      ],
      RequestOptions::AUTH => [$this->schedulerUser->getAccountName(), $this->schedulerUser->passRaw],
      RequestOptions::BODY => Json::encode(['data' => $data]),
    ]);
    $this->assertSame($expected_status, $response->getStatusCode(), (string) $response->getBody());
  }

}
