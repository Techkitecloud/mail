import store from '../../../store'
import logger from '../../../logger'
import dragEventBus from '../util/dragEventBus'

export class DroppableMailbox {

	constructor(el, componentInstance, options) {
		this.el = el
		this.options = options
		this.registerListeners.bind(this)(el)
		this.setInitialAttributes()
	}

	setInitialAttributes() {
		this.draggableInfo = {}
		this.setStatus('enabled')
	}

	update(el, instance) {
		this.setInitialAttributes()
		this.options = instance.options
	}

	registerListeners(el) {
		dragEventBus.$on('dragStart', this.onDragStart.bind(this))
		dragEventBus.$on('dragEnd', this.onDragEnd.bind(this))

		// event listeners need to be attached to the first child element
		// (a button or an anchor tag) instead of the root el, because there
		// can be sub-mailboxes within the root element of the directive
		el.firstChild.addEventListener('dragover', this.onDragOver.bind(this))
		el.firstChild.addEventListener('dragleave', this.onDragLeave.bind(this))
		el.firstChild.addEventListener('drop', this.onDrop.bind(this))
	}

	removeListeners(el) {
		dragEventBus.$off('dragStart', this.onDragStart)
		dragEventBus.$off('dragEnd', this.onDragEnd)

		el.firstChild.removeEventListener('dragover', this.onDragOver)
		el.firstChild.removeEventListener('dragleave', this.onDragLeave)
		el.firstChild.removeEventListener('drop', this.onDrop)
	}

	setStatus(status) {
		this.el.setAttribute('droppable-mailbox', status)
	}

	onDragStart(draggableInfo) {
		this.draggableInfo = draggableInfo

		if (!this.canBeDropped()) {
			this.setStatus('disabled')
		}
	}

	canBeDropped() {
		return this.isSameAccount() && this.options.isValidDropTarget
	}

	isSameAccount() {
		return this.draggableInfo.accountId === this.options.accountId
	}

	onDragEnd() {
		this.setInitialAttributes()
	}

	onDragOver(event) {
		event.preventDefault()

		// Prevent dropping into current folder
		if (this.draggableInfo.mailboxId === this.options.mailboxId) {
			return
		}

		if (this.options.isValidDropTarget) {
			this.setStatus('dragover')
		}

		event.dataTransfer.dropEffect = 'move'
	}

	onDragLeave(event) {
		event.preventDefault()
		this.setStatus('enabled')
	}

	async onDrop(event) {
		event.preventDefault()

		// Prevent dropping into current folder
		if (this.draggableInfo.mailboxId === this.options.mailboxId) {
			return
		}

		this.setInitialAttributes()
		const envelopesBeingDragged = JSON.parse(event.dataTransfer.getData('text'))
		dragEventBus.$emit('envelopesDropped', { envelopes: envelopesBeingDragged })

		try {
			const ids = envelopesBeingDragged.map(envelope => {
				const id = envelope.envelopeId
				const item = document.querySelector(`[data-envelope-id="${id}"]`)
				item.setAttribute('draggable-envelope', 'pending')
				return id
			})

			// Move messages per batch of 50 messages so as to not overload server or create timeouts
			while (ids.length > 0) {
				const batch = ids.splice(-50)
				await store.dispatch('moveMessages', {
					ids: batch,
					destMailboxId: this.options.mailboxId,
				})
			}
		} catch (error) {
			envelopesBeingDragged.map(envelope => {
				const id = envelope.envelopeId
				const item = document.querySelector(`[data-envelope-id="${id}"]`)
				item.removeAttribute('draggable-envelope')
				return id
			})
			logger.error('could not process dropped messages', error)
		} finally {
			dragEventBus.$emit('envelopesMoved', {
				mailboxId: this.options.mailboxId,
				movedEnvelopes: envelopesBeingDragged,
			})
		}
	}

}
