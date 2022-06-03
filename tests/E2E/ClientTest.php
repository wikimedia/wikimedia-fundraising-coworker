<?php

namespace Civi\Coworker\E2E;

use Civi\Coworker\Client\CiviClientInterface;
use Civi\Coworker\Client\CiviPipeClient;
use Civi\Coworker\Client\CiviSessionInterface;
use Civi\Coworker\Client\CiviPoolClient;
use Civi\Coworker\Configuration;
use Civi\Coworker\CoworkerTestTrait;
use Civi\Coworker\PipeConnection;
use Civi\Coworker\PipePool;
use PHPUnit\Framework\TestCase;
use function Clue\React\Block\await;

/**
 * @group e2e
 */
class ClientTest extends TestCase {

  use CoworkerTestTrait;

  protected function setUp(): void {
    $this->setupE2E();
  }

  public function getClients(): array {
    return [
      'CiviPipeClient' => ['createPipeClient'],
      'CiviPoolClient' => ['createPoolClient'],
    ];
  }

  /**
   * Send an APIv3 request.
   *
   * @param string $clientFactory
   * @throws \Exception
   * @dataProvider getClients
   */
  public function testApi3(string $clientFactory) {
    /** @var \Civi\Coworker\Client\CiviClientInterface $client */
    $client = $this->{$clientFactory}();
    $this->assertInstanceOf(CiviClientInterface::class, $client);

    await(
      $client->api3('System', 'get', ['check_permissions' => FALSE])
        ->then(function ($result) {
          $this->assertEquals(PHP_VERSION, $result['values'][0]['php']['version']);
        })
    );
  }

  /**
   * Send an APIv3 request.
   *
   * @param string $clientFactory
   * @throws \Exception
   * @dataProvider getClients
   */
  public function testApi4(string $clientFactory) {
    /** @var \Civi\Coworker\Client\CiviClientInterface $client */
    $client = $this->{$clientFactory}();
    $this->assertInstanceOf(CiviClientInterface::class, $client);

    await(
      $client->api4('Entity', 'get', ['checkPermissions' => FALSE])
        ->then(function ($result) {
          $actualEntities = array_column($result, 'name');
          $this->assertContains('Contact', $actualEntities);
          $this->assertContains('Contribution', $actualEntities);
        })
    );
  }

  /**
   * Lookup a contact and login.
   *
   * @param string $clientFactory
   * @throws \Exception
   * @dataProvider getClients
   */
  public function testLogin(string $clientFactory) {
    /** @var \Civi\Coworker\Client\CiviSessionInterface&\Civi\Coworker\Client\CiviClientInterface $client */
    $client = $this->{$clientFactory}();
    $this->assertInstanceOf(CiviClientInterface::class, $client);
    if (!($client instanceof CiviSessionInterface)) {
      $this->markTestSkipped('Does not apply to clients of type ' . get_class($client));
    }

    $contacts = await($client->api4('Contact', 'get', [
      'select' => ['id', 'display_name'],
      'where' => [['contact_type', '=', 'Individual']],
      'limit' => 1,
      'checkPermissions' => FALSE,
    ]));
    $this->assertNotEmpty($contacts[0]['id']);

    $auth = await($client->login(['contactId' => $contacts[0]['id']]));
    $this->assertEquals($contacts[0]['id'], $auth['contactId'], 'Failed to login with requested contact=' . $contacts[0]['id']);
  }

  protected function createPipeClient(): CiviClientInterface {
    $config = new Configuration([
      'pipeCommand' => $this->cvCmd('pipe'),
    ]);
    $pipe = new PipeConnection($config);
    $client = new CiviPipeClient($config, $pipe);
    await($client->start());
    return $client;
  }

  protected function createPoolClient(): CiviClientInterface {
    $config = new Configuration([
      'pipeCommand' => $this->cvCmd('pipe'),
    ]);
    $pool = new PipePool($config);
    $client = new CiviPoolClient($pool, __CLASS__);
    await($pool->start());
    return $client;
  }

}
