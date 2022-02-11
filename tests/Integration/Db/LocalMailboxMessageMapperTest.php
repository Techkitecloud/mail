<?php

declare(strict_types=1);

/**
 * @copyright 2022 Anna Larch <anna.larch@gmx.net>
 *
 * @author 2022 Anna Larch <anna.larch@gmx.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\Tests\Integration\Db;

use ChristophWurst\Nextcloud\Testing\DatabaseTransaction;
use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Db\LocalAttachmentMapper;
use OCA\Mail\Db\LocalMailboxMessage;
use OCA\Mail\Db\LocalMailboxMessageMapper;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\RecipientMapper;
use OCA\Mail\Tests\Integration\Framework\ImapTestAccount;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;

class LocalMailboxMessageMapperTest extends TestCase {
	use DatabaseTransaction;
	use ImapTestAccount;

	/** @var IDBConnection */
	private $db;

	/** @var MailboxMapper */
	private $mapper;

	/** @var ITimeFactory| MockObject */
	private $timeFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OC::$server->getDatabaseConnection();
		$this->mapper = new LocalMailboxMessageMapper(
			$this->db,
			$this->createMock(LocalAttachmentMapper::class),
			$this->createMock(RecipientMapper::class)
		);

		$qb = $this->db->getQueryBuilder();

		$delete = $qb->delete($this->mapper->getTableName());
		$delete->execute();


		$acct = $this->createTestAccount();

		$message = new LocalMailboxMessage();
		$message->setType(LocalMailboxMessage::OUTGOING);
		$message->setAccountId($acct->getId());
		$message->setAliasId(2);
		$message->setSendAt(123);
		$message->setSubject('subject');
		$message->setBody('message');
		$message->setHtml(true);
		$message->setInReplyToId(100);
		$message->setDraftId(99);

		$this->mapper->insert($message);
	}

	public function testFindAllForUser(): void {
		$userdId = $this->getTestAccountUserId();
		$result = $this->mapper->getAllForUser($userdId);
		$this->assertCount(1, $result);

		$row = $result[0];

		$this->assertEquals(LocalMailboxMessage::OUTGOING, $row['type']);
		$this->assertEquals(2, $row['alias_id']);
		$this->assertEquals(123, $row['send_at']);
		$this->assertEquals('subject', $row['subject']);
		$this->assertEquals('message', $row['body']);
		$this->assertEquals(100, $row['in_reply_to_id']);
		$this->assertEquals(99, $row['draft_id']);
		$this->assertTrue((bool)$row['html']);
	}
}
