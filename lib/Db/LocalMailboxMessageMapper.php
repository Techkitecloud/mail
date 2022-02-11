<?php

declare(strict_types=1);

/**
 * @copyright 2022 Anna Larch <anna@nextcloud.com>
 *
 * @author 2022 Anna Larch <anna@nextcloud.com>
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

namespace OCA\Mail\Db;

use OCP\DB\Exception as DBException;
use function array_map;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<LocalMailboxMessage>
 */
class LocalMailboxMessageMapper extends QBMapper {
	/** @var LocalAttachmentMapper */
	private $attachmentMapper;

	/** @var RecipientMapper */
	private $recipientMapper;

	public function __construct(IDBConnection $db,
								LocalAttachmentMapper $attachmentMapper,
								RecipientMapper $recipientMapper) {
		parent::__construct($db, 'mail_local_mailbox');
		$this->recipientMapper = $recipientMapper;
		$this->attachmentMapper = $attachmentMapper;
	}

	/**
	 * @param string $userId
	 * @return LocalMailboxMessage[]
	 * @throws DBException
	 */
	public function getAllForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.*')
			->from('mail_accounts', 'a')
			->join('a', $this->getTableName(), 'm', $qb->expr()->eq('m.account_id', 'a.id'))
			->where(
				$qb->expr()->eq('a.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('m.type', $qb->createNamedParameter(LocalMailboxMessage::OUTGOING, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		$rows = $qb->execute();
		$result = $rows->fetchAll();
		$rows->closeCursor();
		return $result;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): LocalMailboxMessage {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		return $this->findEntity($qb);
	}

	public function getRelatedData(int $id, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('a.*')
			->from('mail_lcl_mbx_attchmts', 'm')
			->join('m', 'mail_attachments', 'a', $qb->expr()->eq('m.attachment_id', 'a.id'))
			->where(
				$qb->expr()->eq('a.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('m.local_message_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		$rows = $qb->execute();
		$result = $rows->fetchAll();
		$rows->closeCursor();
		$related = [];
		$related['attachments'] = $result;
		$related['recipients'] = $this->recipientMapper->findRecipients($id, Recipient::MAILBOX_TYPE_OUTBOX);
		return $related;
	}

	/**
	 * @throws DBException
	 */
	public function saveWithRelatedData(LocalMailboxMessage $message, array $to, array $cc, array $bcc, array $attachmentIds = []): void {
		$this->insert($message);
		// @TODO
		// not that easy - this needs work to actually create the right recipient type in the DB!
		array_map(function ($recipient) use ($message) {
			$this->recipientMapper->createForLocalMailbox($message->getId(), $recipient['label'] ?? $recipient['email'], $recipient['email']);
		}, array_merge($to, $cc, $bcc));
		foreach ($attachmentIds as $attachmentId) {
			$this->attachmentMapper->linkAttachmentToMessage($message->getId(), $attachmentId);
		}
	}

	/**
	 * @throws DBException
	 */
	public function deleteWithRelated(LocalMailboxMessage $message, string $userId): void {
		$this->attachmentMapper->deleteForLocalMailbox($message->getId(), $userId);
		$this->recipientMapper->deleteForLocalMailbox($message->getId());
		$this->delete($message);
	}
}
