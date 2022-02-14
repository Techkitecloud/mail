<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Luc Calaresu <dev@calaresu.com>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<LocalAttachment>
 */
class LocalAttachmentMapper extends QBMapper {
	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db, ITimeFactory $timeFactory) {
		parent::__construct($db, 'mail_attachments');
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @return LocalAttachment[]
	 */
	public function findForLocalMailboxMessage(int $localMessageId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('a.*')
			->from('mail_lcl_mbx_attchmts', 'm')
			->join('m', 'mail_attachments', 'a', $qb->expr()->eq('m.attachment_id', 'a.id'))
			->where(
				$qb->expr()->eq('a.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('m.local_message_id', $qb->createNamedParameter($localMessageId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		return $this->findEntities($qb);
	}

	public function findAllForLocalMailbox(array $localMessageIds, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('a.*', 'm.local_message_id')
			->from('mail_lcl_mbx_attchmts', 'm')
			->join('m', 'mail_attachments', 'a', $qb->expr()->eq('m.attachment_id', 'a.id'))
			->where(
				$qb->expr()->eq('a.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->in('m.local_message_id', $qb->createNamedParameter($localMessageIds, IQueryBuilder::PARAM_INT_ARRAY), IQueryBuilder::PARAM_INT_ARRAY)
			);
		$rows = $qb->execute();
		$result = $rows->fetchAll();
		$rows->closeCursor();
		return $result;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(string $userId, int $id): LocalAttachment {
		$qb = $this->db->getQueryBuilder();
		$query = $qb
			->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));

		return $this->findEntity($query);
	}

	/**
	 * @throws Exception
	 */
	public function findRows(string $userId, int $id): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		$result = $qb->execute();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	public function deleteForLocalMailbox(int $localMessageId): void {
		// make this a sub query
		$qb = $this->db->getQueryBuilder();
		$qb->select('attachment_id')
			->from('mail_lcl_mbx_attchmts')
			->where($qb->expr()->eq('local_message_id', $qb->createNamedParameter($localMessageId), IQueryBuilder::PARAM_INT));

		$qb1 = $this->db->getQueryBuilder();
		$qb1->delete($this->getTableName())
			->where($qb1->expr()->in('id', $qb1->createFunction($qb->getSQL()), IQueryBuilder::PARAM_INT_ARRAY), IQueryBuilder::PARAM_INT_ARRAY);
		$result = $qb1->execute();
		$result->closeCursor();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->delete('mail_lcl_mbx_attchmts')
			->where($qb2->expr()->eq('local_message_id', $qb2->createNamedParameter($localMessageId), IQueryBuilder::PARAM_INT));
		$result = $qb2->execute();
		$result->closeCursor();
	}

	public function createLocalMailboxAttachment(int $localMessageId, string $userId, string $fileName, string $mimetype): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->setValue('user_id', $qb->createNamedParameter($userId))
			->setValue('created_at', $qb->createNamedParameter($this->timeFactory->getTime()))
			->setValue('file_name', $qb->createNamedParameter($fileName))
			->setValue('mime_type', $qb->createNamedParameter($mimetype));
		$result = $qb->execute();
		$attachmentId = $qb->getLastInsertId();
		$result->closeCursor();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->insert('mail_lcl_mbx_attchmts')
			->setValue('local_message_id', $qb2->createNamedParameter($localMessageId))
			->setValue('attachment_id', $qb2->createNamedParameter($attachmentId));
		$result = $qb2->execute();
		$result->closeCursor();
	}

	public function linkAttachmentToMessage(int $messageId, array $attachmentIds): void {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('mail_lcl_mbx_attchmts')
			->setValue('message_id', $qb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT));
		$qb->setValue('attachment_id', $qb->createParameter('attachmentId'));

		array_map(static function ($attachmentId) use ($qb) {
			$qb->setParameter('attachmentId', $attachmentId);
			$result = $qb->execute();
			$result->closeCursor();
		}, $attachmentIds);
	}
}
