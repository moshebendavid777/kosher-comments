(() => {
	const root = document.querySelector('.kosher-comments');

	if (!root) {
		return;
	}

	const config = window.kosherComments || {};
	const list = root.querySelector('[data-comments-list]');
	const feedback = root.querySelector('.kosher-comments-feedback');
	const toastStack = root.querySelector('.kosher-comments-toast-stack');
	const ratingModal = root.querySelector('.kosher-comments-rating-modal');
	const reportModal = root.querySelector('.kosher-comments-report-modal');
	const reportForm = root.querySelector('.kosher-comments-report-form');
	const reportCount = reportForm?.querySelector('[data-report-count]');
	const alertModal = root.querySelector('.kosher-comments-alert-modal');
	const alertTitle = alertModal?.querySelector('.kosher-comments-alert-title');
	const alertMessage = alertModal?.querySelector('.kosher-comments-alert-message');
	const alertConfirm = alertModal?.querySelector('[data-alert-confirm]');
	const alertCancel = alertModal?.querySelector('[data-alert-cancel]');
	const editRatingModal = root.querySelector('.kosher-comments-edit-rating-modal');
	const editRatingPicker = editRatingModal?.querySelector('[data-edit-rating-picker]');
	const photoModal = root.querySelector('.kosher-comments-photo-modal');
	const submitOverlay = root.querySelector('.kosher-comments-submit-overlay');
	const overlayTitle = submitOverlay?.querySelector('.kosher-comments-submit-title');
	const overlayDetail = submitOverlay?.querySelector('.kosher-comments-submit-detail');
	const loadMoreButton = root.querySelector('.kosher-comments-load-more');
	const jumpButton = root.querySelector('.kosher-comments-jump-form');
	const mainForm = root.querySelector('.kosher-comments-form-main');
	const modalRatingPicker = ratingModal?.querySelector('[data-modal-rating-picker]');
	let pendingRatingForm = null;
	let activePhotoGroups = [];
	let activePhotoGroupIndex = 0;
	let activePhotoIndex = 0;
	let activePhotoCollectionLabel = '';
	let activeDialogResolve = null;

	const getCurrentPage = () => Number(root.dataset.currentPage || 1);
	const setCurrentPage = (page) => {
		root.dataset.currentPage = String(page);
	};

	const renderStars = (rating) => {
		const value = Math.max(0, Math.min(5, Math.round(Number(rating || 0) * 2) / 2));

		return Array.from({ length: 5 }, (_, index) => {
			const starValue = value - index;
			const state = starValue >= 1 ? ' is-filled' : starValue >= 0.5 ? ' is-partial' : ' is-empty';

			return `<span class="kosher-comments-star bi bi-star-fill${state}" aria-hidden="true"></span>`;
		}).join('');
	};

	const updateRatingSummary = (summary) => {
		const card = root.querySelector('[data-rating-summary]');

		if (!card || !summary) {
			return;
		}

		const average = Number(summary.averageRating || 0);
		const count = Number(summary.ratingsCount || 0);
		const score = card.querySelector('[data-rating-summary-score]');
		const averageElement = card.querySelector('[data-rating-average]');
		const countElement = card.querySelector('[data-rating-count]');
		const stars = score?.querySelector('.kosher-comments-stars');

		if (stars) {
			stars.setAttribute('aria-label', `${average.toFixed(1)} out of 5 stars`);
			stars.innerHTML = renderStars(average);
		}

		if (averageElement) {
			averageElement.textContent = average.toFixed(1);
		}

		if (countElement) {
			countElement.textContent = `${count} global ${count === 1 ? 'rating' : 'ratings'}`;
		}

		Object.entries(summary.ratingBars || {}).forEach(([rating, bar]) => {
			const row = card.querySelector(`[data-rating-row="${rating}"]`);
			const percent = Math.max(0, Math.min(100, Number(bar?.percent || 0)));

			if (!row) {
				return;
			}

			const barElement = row.querySelector('[data-rating-percent-bar]');
			const textElement = row.querySelector('[data-rating-percent]');

			if (barElement) {
				barElement.style.width = `${percent}%`;
			}

			if (textElement) {
				textElement.textContent = `${percent}%`;
			}
		});
	};

	const updateRatingButtonState = (button, isActive) => {
		button.classList.toggle('is-active', isActive);
	};

	const updateVoteButtonState = (button, isActive) => {
		button.classList.toggle('is-active', isActive);

		const icon = button.querySelector('.kosher-comments-vote-icon');

		if (!icon) {
			return;
		}

		const isLike = button.dataset.voteType === 'like';
		const inactiveClass = isLike ? 'bi-hand-thumbs-up' : 'bi-hand-thumbs-down';
		const activeClass = isLike ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-down-fill';

		icon.classList.toggle(activeClass, isActive);
		icon.classList.toggle(inactiveClass, !isActive);
	};

	const getAvatarInitials = (name) => {
		const clean = String(name || '').replace(/[^\p{L}\p{N}]+/gu, '').trim();
		const fallback = clean || 'AN';

		return fallback.slice(0, 2).toUpperCase();
	};

	const getMiniEditorSettings = () => ({
		tinymce: {
			toolbar1: 'bold,italic,bullist,numlist,blockquote,link,undo,redo',
			toolbar2: '',
			statusbar: false,
			resize: false,
			branding: false,
			wp_autoresize_on: true,
		},
		quicktags: false,
		mediaButtons: false,
	});

	const getFormCommentField = (form) => form?.querySelector('[name="comment_text"]') || null;

	const ensureMiniEditor = (form) => {
		const field = getFormCommentField(form);
		const editorId = field?.id;

		if (!editorId || !window.wp?.editor?.initialize) {
			return;
		}

		if (window.tinymce?.get(editorId) || document.getElementById(`wp-${editorId}-wrap`)) {
			return;
		}

		window.wp.editor.initialize(editorId, getMiniEditorSettings());

		window.setTimeout(() => {
			if (window.switchEditors?.go) {
				window.switchEditors.go(editorId, 'tmce');
			}
		}, 0);
	};

	const removeMiniEditor = (form) => {
		const editorId = getFormCommentField(form)?.id;

		if (!editorId) {
			return;
		}

		if (window.wp?.editor?.remove) {
			window.wp.editor.remove(editorId);
			return;
		}

		if (window.tinymce?.get(editorId)) {
			window.tinymce.get(editorId).remove();
		}
	};

	const getFormEditor = (form) => {
		const field = getFormCommentField(form);
		const editorId = field?.id;

		if (!editorId || !window.tinymce?.get) {
			return null;
		}

		return window.tinymce.get(editorId);
	};

	const syncFormEditor = (form) => {
		const editor = getFormEditor(form);

		if (editor && !editor.isHidden()) {
			editor.save();
		}
	};

	const getFormCommentValue = (form) => {
		syncFormEditor(form);
		return getFormCommentField(form)?.value.trim() || '';
	};

	const resetFormCommentField = (form) => {
		const field = getFormCommentField(form);
		const editor = getFormEditor(form);

		if (editor && typeof editor.setContent === 'function') {
			editor.setContent('');
			editor.save();
		}

		if (field) {
			field.value = '';
		}
	};

	const focusFormCommentField = (form) => {
		const editor = getFormEditor(form);

		if (editor && !editor.isHidden()) {
			editor.focus();
			return;
		}

		getFormCommentField(form)?.focus();
	};

	const refreshBodyModalState = () => {
		const hasOpenLayer = [ratingModal, editRatingModal, reportModal, alertModal, photoModal, submitOverlay].some((node) => node && !node.hidden);
		document.body.classList.toggle('kosher-comments-has-modal', hasOpenLayer);
	};

	const showFeedback = (message, type = 'info') => {
		if (!feedback) {
			return;
		}

		feedback.textContent = message || '';
		feedback.className = `kosher-comments-feedback is-${type}`;
	};

	const showToast = (message, type = 'info') => {
		if (!toastStack || !message) {
			showFeedback(message, type);
			return;
		}

		const toast = document.createElement('div');
		toast.className = `kosher-comments-toast is-${type}`;
		toast.innerHTML = `
			<div class="kosher-comments-toast-copy">
				<strong>${type === 'error' ? 'Action needed' : type === 'success' ? 'Done' : 'Notice'}</strong>
				<span>${message}</span>
			</div>
			<button type="button" class="kosher-comments-toast-close" aria-label="Dismiss">&times;</button>
		`;

		const removeToast = () => {
			toast.classList.add('is-leaving');
			window.setTimeout(() => toast.remove(), 220);
		};

		toast.querySelector('.kosher-comments-toast-close')?.addEventListener('click', removeToast);
		toastStack.appendChild(toast);
		window.setTimeout(removeToast, type === 'error' ? 5200 : 3600);
	};

	const closeAlertModal = (result = false) => {
		if (alertModal) {
			alertModal.hidden = true;
		}

		if (activeDialogResolve) {
			activeDialogResolve(result);
			activeDialogResolve = null;
		}

		refreshBodyModalState();
	};

	const openAlertModal = ({
		title = config.strings?.dialogTitle || 'Kosher Comments',
		message = '',
		confirmText = config.strings?.dialogConfirm || 'Okay',
		cancelText = config.strings?.dialogCancel || 'Cancel',
		confirm = false,
	}) => new Promise((resolve) => {
		if (!alertModal || !alertTitle || !alertMessage || !alertConfirm || !alertCancel) {
			resolve(window.confirm(message));
			return;
		}

		activeDialogResolve = resolve;
		alertTitle.textContent = title;
		alertMessage.textContent = message;
		alertConfirm.textContent = confirmText;
		alertCancel.textContent = cancelText;
		alertCancel.hidden = !confirm;
		alertModal.hidden = false;
		refreshBodyModalState();
	});

	const showSubmitOverlay = () => {
		if (!submitOverlay || !overlayTitle || !overlayDetail) {
			return;
		}

		overlayTitle.textContent = config.strings?.postingPrepare || 'Your comment will be posted in a moment...';
		overlayDetail.textContent = config.strings?.postingPrepareDetail || '';
		submitOverlay.hidden = false;
		refreshBodyModalState();
	};

	const hideSubmitOverlay = (type = 'success', message = '') => {
		if (!submitOverlay || !overlayTitle || !overlayDetail) {
			return;
		}

		if (type === 'error') {
			overlayTitle.textContent = config.strings?.postingRejected || 'Comment could not be posted';
			overlayDetail.textContent = message || config.strings?.postingRejectedDetail || 'The comment was rejected or needs another try.';
		}

		window.setTimeout(() => {
			submitOverlay.hidden = true;
			refreshBodyModalState();
		}, type === 'error' ? 950 : 520);
	};

	const lockForm = (form, locked) => {
		if (!form) {
			return;
		}

		form.dataset.busy = locked ? '1' : '';
		form.classList.toggle('is-busy', locked);

		form.querySelectorAll('button, input, textarea').forEach((node) => {
			if (node instanceof HTMLButtonElement || node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
				if (node.type !== 'hidden') {
					node.disabled = locked;
				}
			}
		});

		getFormCommentField(form)?.closest('.wp-editor-wrap')?.classList.toggle('is-disabled', locked);
	};

	const updateFileLabel = (form) => {
		const output = form?.querySelector('.kosher-comments-selected-files');
		const input = form?.querySelector('input[type="file"]');

		if (!output || !input) {
			return;
		}

		if (!input.files.length) {
			output.classList.add('is-empty');
			output.innerHTML = '';
			return;
		}

		const files = Array.from(input.files);
		const previewFiles = files.slice(0, 3);
		const remainingCount = Math.max(files.length - previewFiles.length, 0);
		const thumbs = previewFiles.map((file) => {
			const url = URL.createObjectURL(file);
			return `<span class="kosher-comments-selected-file-thumb"><img src="${url}" alt="${file.name.replace(/"/g, '&quot;')}"></span>`;
		}).join('');
		const more = remainingCount > 0 ? `<span class="kosher-comments-selected-file-more">+${remainingCount}</span>` : '';
		const summary = `${files.length} image${files.length === 1 ? '' : 's'} selected`;

		output.classList.remove('is-empty');
		output.innerHTML = `
			<span class="kosher-comments-selected-file-list">${thumbs}${more}</span>
			<span class="kosher-comments-selected-file-text">${summary}</span>
		`;
	};

	const openRatingModal = () => {
		if (ratingModal) {
			if (modalRatingPicker) {
				setRating(modalRatingPicker, 0);
				modalRatingPicker.classList.remove('is-emphasized');
			}

			ratingModal.hidden = false;
			refreshBodyModalState();
		}
	};

	const closeRatingModal = () => {
		if (ratingModal) {
			ratingModal.hidden = true;
			refreshBodyModalState();
		}
	};

	const openEditRatingModal = () => {
		const state = mainForm?.querySelector('[data-user-rated-state]');
		const rating = Number(state?.dataset.userRating || 0);

		if (!editRatingModal || !editRatingPicker || !rating) {
			return;
		}

		setRating(editRatingPicker, rating);
		editRatingPicker.classList.remove('is-emphasized');
		editRatingModal.hidden = false;
		refreshBodyModalState();
	};

	const closeEditRatingModal = () => {
		if (editRatingModal) {
			editRatingModal.hidden = true;
			refreshBodyModalState();
		}
	};

	const openReportModal = (payload) => {
		if (!reportModal || !reportForm) {
			return;
		}

		reportForm.reset();
		reportForm.querySelector('[name="report_type"]').value = payload.reportType || '';
		reportForm.querySelector('[name="comment_id"]').value = payload.commentId || '';
		reportForm.querySelector('[name="image_id"]').value = payload.imageId || '';
		updateReportCount();
		reportModal.hidden = false;
		refreshBodyModalState();
		window.setTimeout(() => reportForm.querySelector('[name="subject"]')?.focus(), 20);
	};

	const closeReportModal = () => {
		if (reportModal) {
			reportModal.hidden = true;
			refreshBodyModalState();
		}
	};

	const updateReportCount = () => {
		if (!reportForm || !reportCount) {
			return;
		}

		const textarea = reportForm.querySelector('[name="reason"]');
		const value = textarea?.value || '';
		reportCount.textContent = `${value.length}/140`;
	};

	const closePhotoModal = () => {
		if (photoModal) {
			photoModal.hidden = true;
			const image = photoModal.querySelector('.kosher-comments-photo-image');

			if (image) {
				image.removeAttribute('src');
			}
		}

		refreshBodyModalState();
	};

	const buildPhotoItems = (buttons) => buttons.map((button) => ({
		imageId: button.dataset.imageId || '',
		commentId: button.dataset.commentId || '',
		url: button.dataset.photoUrl || '',
		thumb: button.dataset.photoThumb || '',
		authorName: button.dataset.authorName || '',
		avatarUrl: button.dataset.avatarUrl || '',
		rating: button.dataset.rating || '0',
		excerpt: button.dataset.excerpt || '',
		shareUrl: button.dataset.shareUrl || '',
	}));

	const buildCommentPhotoGroups = (trigger) => {
		const comments = Array.from(root.querySelectorAll('.kosher-comment'));
		const groups = comments
			.map((comment) => {
				const collection = comment.querySelector('.kosher-comment-images[data-photo-collection]');

				if (!collection) {
					return null;
				}

				const buttons = Array.from(collection.querySelectorAll('[data-open-photo-modal]'));

				if (!buttons.length) {
					return null;
				}

				return {
					label: collection.dataset.photoCollectionLabel || '',
					items: buildPhotoItems(buttons),
					element: collection,
				};
			})
			.filter(Boolean);
		const currentCollection = trigger.closest('[data-photo-collection]');
		const groupIndex = Math.max(0, groups.findIndex((group) => group.element === currentCollection));
		const currentButtons = currentCollection ? Array.from(currentCollection.querySelectorAll('[data-open-photo-modal]')) : [];
		const index = Math.max(0, currentButtons.indexOf(trigger));

		return {
			groups,
			groupIndex,
			index,
		};
	};

	const buildStripPhotoGroups = (trigger) => {
		const collection = trigger.closest('[data-photo-collection]');

		if (!collection) {
			return {
				groups: [],
				groupIndex: 0,
				index: 0,
			};
		}

		const buttons = Array.from(collection.querySelectorAll('[data-open-photo-modal]'));

		if (!buttons.length) {
			return {
				groups: [],
				groupIndex: 0,
				index: 0,
			};
		}

		const groups = [];
		let currentGroup = null;

		buttons.forEach((button) => {
			const commentId = button.dataset.commentId || '';

			if (!currentGroup || currentGroup.commentId !== commentId) {
				currentGroup = {
					commentId,
					label: collection.dataset.photoCollectionLabel || '',
					items: [],
				};
				groups.push(currentGroup);
			}

			currentGroup.items.push(...buildPhotoItems([button]));
		});

		let groupIndex = 0;
		let index = 0;

		groups.some((group, currentIndex) => {
			const itemIndex = group.items.findIndex((item) => item.imageId === (trigger.dataset.imageId || ''));

			if (itemIndex >= 0) {
				groupIndex = currentIndex;
				index = itemIndex;
				return true;
			}

			return false;
		});

		return {
			groups,
			groupIndex,
			index,
		};
	};

	const buildPhotoCollection = (trigger) => {
		if (trigger.closest('.kosher-comment-images')) {
			return buildCommentPhotoGroups(trigger);
		}

		return buildStripPhotoGroups(trigger);
	};

	const getActivePhotoGroup = () => activePhotoGroups[activePhotoGroupIndex] || null;

	const getActivePhotoItem = () => {
		const group = getActivePhotoGroup();

		if (!group) {
			return null;
		}

		return group.items[activePhotoIndex] || null;
	};

	const getPhotoNavigationState = () => {
		const group = getActivePhotoGroup();

		if (!group) {
			return { hasPrev: false, hasNext: false };
		}

		return {
			hasPrev: activePhotoIndex > 0 || activePhotoGroupIndex > 0,
			hasNext: activePhotoIndex < (group.items.length - 1) || activePhotoGroupIndex < (activePhotoGroups.length - 1),
		};
	};

	const renderPhotoModal = () => {
		const group = getActivePhotoGroup();
		const item = getActivePhotoItem();

		if (!photoModal || !group || !item) {
			return;
		}

		const image = photoModal.querySelector('.kosher-comments-photo-image');
		const avatar = photoModal.querySelector('.kosher-comments-photo-avatar');
		const avatarFallback = photoModal.querySelector('.kosher-comments-photo-avatar-fallback');
		const name = photoModal.querySelector('.kosher-comments-photo-name');
		const rating = photoModal.querySelector('.kosher-comments-photo-rating');
		const excerpt = photoModal.querySelector('.kosher-comments-photo-excerpt');
		const thumbs = photoModal.querySelector('.kosher-comments-photo-thumbs');
		const shareButton = photoModal.querySelector('.kosher-comments-photo-share');
		const reportButton = photoModal.querySelector('.kosher-comments-photo-report');
		const counter = photoModal.querySelector('.kosher-comments-photo-counter');
		const heading = photoModal.querySelector('.kosher-comments-photo-heading strong');
		const prevButton = photoModal.querySelector('[data-photo-nav="prev"]');
		const nextButton = photoModal.querySelector('[data-photo-nav="next"]');
		const navigation = getPhotoNavigationState();

		if (image) {
			image.src = item.url;
			image.alt = item.authorName ? `${item.authorName} review image` : 'Review image';
		}

		if (avatar) {
			avatar.hidden = false;
			avatar.src = item.avatarUrl;
			avatar.alt = '';
			avatar.onerror = () => {
				avatar.hidden = true;
			};
		}

		if (avatarFallback) {
			avatarFallback.textContent = getAvatarInitials(item.authorName);
		}

		if (name) {
			name.textContent = item.authorName;
		}

		if (rating) {
			if (Number(item.rating || 0) > 0) {
				rating.innerHTML = renderStars(item.rating);
				rating.hidden = false;
			} else {
				rating.innerHTML = '';
				rating.hidden = true;
			}
		}

		if (excerpt) {
			excerpt.textContent = item.excerpt;
		}

		if (shareButton) {
			shareButton.dataset.shareUrl = item.shareUrl;
		}

		if (reportButton) {
			reportButton.dataset.commentId = item.commentId;
			reportButton.dataset.imageId = item.imageId;
			reportButton.dataset.reportType = 'image';
		}

		if (counter) {
			counter.textContent = `${activePhotoIndex + 1} / ${group.items.length}`;
		}

		if (heading) {
			heading.textContent = group.label || activePhotoCollectionLabel || (config.strings?.photoCollectionDefault || 'All photos');
		}

		if (prevButton) {
			prevButton.hidden = !navigation.hasPrev;
		}

		if (nextButton) {
			nextButton.hidden = !navigation.hasNext;
		}

		if (thumbs) {
			thumbs.innerHTML = group.items.map((photo, index) => (
				`<button type="button" class="kosher-comments-photo-mini${index === activePhotoIndex ? ' is-active' : ''}" data-photo-thumb-index="${index}">
					<img src="${photo.thumb}" alt="">
				</button>`
			)).join('');
		}
	};

	const openPhotoModal = (trigger) => {
		const collection = buildPhotoCollection(trigger);
		activePhotoGroups = collection.groups;
		activePhotoGroupIndex = collection.groupIndex;
		activePhotoCollectionLabel = config.strings?.photoCollectionDefault || 'All photos';
		activePhotoIndex = collection.index;

		if (!activePhotoGroups.length || !photoModal) {
			return;
		}

		renderPhotoModal();
		photoModal.hidden = false;
		refreshBodyModalState();
	};

	const movePhotoModal = (direction) => {
		const group = getActivePhotoGroup();

		if (!group) {
			return;
		}

		if (direction > 0) {
			if (activePhotoIndex < group.items.length - 1) {
				activePhotoIndex += 1;
			} else if (activePhotoGroupIndex < activePhotoGroups.length - 1) {
				activePhotoGroupIndex += 1;
				activePhotoIndex = 0;
			} else {
				return;
			}
		} else if (direction < 0) {
			if (activePhotoIndex > 0) {
				activePhotoIndex -= 1;
			} else if (activePhotoGroupIndex > 0) {
				activePhotoGroupIndex -= 1;
				activePhotoIndex = Math.max(0, activePhotoGroups[activePhotoGroupIndex].items.length - 1);
			} else {
				return;
			}
		}

		renderPhotoModal();
	};

	const setRating = (picker, value) => {
		const hiddenField = picker.querySelector('input[name="rating"]');
		const buttons = picker.querySelectorAll('[data-rating]');

		if (hiddenField) {
			hiddenField.value = value;
		}

		buttons.forEach((button) => {
			updateRatingButtonState(button, Number(button.dataset.rating) <= Number(value));
		});
	};

	const applyUserRatedState = (ratingValue) => {
		const rating = Number(ratingValue || 0);
		const toolbar = mainForm?.querySelector('.kosher-comments-form-toolbar');
		const existingState = toolbar?.querySelector('.kosher-comments-user-rated');
		const picker = toolbar?.querySelector('[data-rating-picker]');
		const hiddenField = mainForm?.querySelector('input[name="rating"]');

		if (!toolbar || !rating) {
			return;
		}

		root.dataset.userHasRated = '1';

		if (hiddenField) {
			hiddenField.value = '';
		}

		if (picker) {
			picker.remove();
		}

		if (existingState) {
			existingState.dataset.userRating = String(rating);
			existingState.innerHTML = `
				<span class="kosher-comments-user-rated-label">${config.strings?.userRatedThis || 'You rated this'}</span>
				<span class="kayco-recipe-rating__stars kosher-comments-user-rated-stars">${renderStars(rating)}</span>
				<button type="button" class="kosher-comments-user-rated-edit" data-edit-user-rating>${config.strings?.editRating || 'Edit'}</button>
			`;
			return;
		}

		toolbar.insertAdjacentHTML(
			'afterbegin',
			`<div class="kosher-comments-user-rated" data-user-rated-state data-user-rating="${rating}">
				<span class="kosher-comments-user-rated-label">${config.strings?.userRatedThis || 'You rated this'}</span>
				<span class="kayco-recipe-rating__stars kosher-comments-user-rated-stars">${renderStars(rating)}</span>
				<button type="button" class="kosher-comments-user-rated-edit" data-edit-user-rating>${config.strings?.editRating || 'Edit'}</button>
			</div>`
		);
	};

	const serializeForm = (form, options = {}) => {
		const formData = new FormData();
		const parentId = Number(form.dataset.parentId || 0);
		const commentText = getFormCommentValue(form);
		const ratingOnly = options.ratingOnly === true;
		const notifyReplies = form.querySelector('[name="notify_replies"]')?.checked ? '1' : '';
		const isQuestion = parentId === 0 && form.querySelector('[name="is_question"]')?.checked ? '1' : '';
		const rating = parentId === 0 ? form.querySelector('[name="rating"]')?.value || '' : '';

		formData.append('action', 'kosher_comments_submit_comment');
		formData.append('nonce', config.nonce || '');
		formData.append('post_id', root.dataset.postId || '');
		formData.append('parent_id', String(parentId));
		formData.append('comment_text', ratingOnly ? '' : commentText);
		formData.append('notify_replies', ratingOnly ? '' : notifyReplies);
		formData.append('is_question', ratingOnly ? '' : isQuestion);

		if (ratingOnly) {
			formData.append('rating_only', '1');
		}

		if (rating) {
			formData.append('rating', rating);
		}

		const fileInput = form.querySelector('input[type="file"]');

		if (fileInput && parentId === 0 && !ratingOnly) {
			Array.from(fileInput.files || []).forEach((file) => {
				formData.append('images[]', file);
			});
		}

		return formData;
	};

	const resetForm = (form) => {
		form.reset();
		resetFormCommentField(form);
		form.dataset.skipPrompt = '';
		const picker = form.querySelector('[data-rating-picker]');

		if (picker) {
			setRating(picker, 0);
		}

		updateFileLabel(form);
	};

	const appendComment = (payload) => {
		if (!payload?.html) {
			return;
		}

		if (Number(payload.parentId || 0) > 0) {
			const parent = root.querySelector(`[data-replies-container="${payload.parentId}"]`);

			if (parent) {
				parent.insertAdjacentHTML('beforeend', payload.html);
				parent.hidden = false;
			}
		} else if (list) {
			list.insertAdjacentHTML('afterbegin', payload.html);
		}

		const inserted = root.querySelector(`#kosher-comment-${payload.commentId}`);

		if (inserted) {
			inserted.classList.add('is-new');
			window.setTimeout(() => inserted.classList.remove('is-new'), 1400);
		}

		bindPhotoTriggers(root);
	};

	const postForm = async (form, options = {}) => {
		if (form.dataset.busy === '1') {
			return;
		}

		const submittedRating = Number(form.querySelector('[name="rating"]')?.value || 0);
		const ratingOnly = options.ratingOnly === true;
		lockForm(form, true);
		showSubmitOverlay();

		try {
			const response = await fetch(config.ajaxUrl, {
				method: 'POST',
				body: serializeForm(form, { ratingOnly }),
				credentials: 'same-origin',
			});
			const result = await response.json();

			if (!result.success) {
				hideSubmitOverlay('error', result.data?.message || config.strings?.postError || 'Unable to post your comment.');
				showFeedback(result.data?.message || config.strings?.postError || 'Unable to post your comment.', 'error');
				showToast(result.data?.message || config.strings?.postError || 'Unable to post your comment.', 'error');
				return;
			}

			hideSubmitOverlay('success');
			showFeedback(result.data?.message || config.strings?.postSuccess || 'Comment posted.', 'success');
			showToast(result.data?.message || config.strings?.postSuccess || 'Comment posted.', 'success');
			if (!result.data?.ratingOnly) {
				appendComment(result.data);
			}

			if (Number(form.dataset.parentId || 0) === 0 && submittedRating > 0) {
				applyUserRatedState(submittedRating);
			}

			updateRatingSummary(result.data?.summary);

			if (!result.data?.ratingOnly) {
				resetForm(form);
			}

			if (form.classList.contains('kosher-comments-reply-form')) {
				form.hidden = true;
			}
		} catch (error) {
			hideSubmitOverlay('error', config.strings?.networkError || 'The network request failed. Please try again.');
			showFeedback(config.strings?.networkError || 'The network request failed. Please try again.', 'error');
			showToast(config.strings?.networkError || 'The network request failed. Please try again.', 'error');
		} finally {
			lockForm(form, false);
		}
	};

	const loadComments = async (nextPage) => {
		const formData = new FormData();
		formData.append('action', 'kosher_comments_load_comments');
		formData.append('nonce', config.nonce || '');
		formData.append('post_id', root.dataset.postId || '');
		formData.append('page', String(nextPage));
		formData.append('target_comment_id', root.dataset.targetCommentId || '');

		try {
			const response = await fetch(config.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			});
			const result = await response.json();

			if (!result.success) {
				showToast(config.strings?.loadMoreError || 'Unable to load more comments right now.', 'error');
				return null;
			}

			return result.data;
		} catch (error) {
			showToast(config.strings?.loadMoreError || 'Unable to load more comments right now.', 'error');
			return null;
		}
	};

	const highlightComment = (commentId) => {
		if (!commentId) {
			return false;
		}

		const target = root.querySelector(`#kosher-comment-${commentId}`);

		if (!target) {
			return false;
		}

		root.querySelectorAll('.kosher-comment.is-highlighted').forEach((node) => {
			if (node !== target) {
				node.classList.remove('is-highlighted');
			}
		});

		target.classList.add('is-highlighted');
		target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		return true;
	};

	const ensureSharedCommentVisible = async () => {
		const targetCommentId = Number(root.dataset.targetCommentId || 0);
		const targetPage = Number(root.dataset.targetPage || 1);

		if (!targetCommentId) {
			return;
		}

		if (highlightComment(targetCommentId)) {
			return;
		}

		while (loadMoreButton && !loadMoreButton.hidden && getCurrentPage() < targetPage) {
			const nextPage = getCurrentPage() + 1;
			const payload = await loadComments(nextPage);

			if (!payload) {
				break;
			}

			if (list) {
				list.insertAdjacentHTML('beforeend', payload.html || '');
				bindPhotoTriggers(list);
			}

			setCurrentPage(payload.page || nextPage);

			if (!payload.hasMore) {
				loadMoreButton.hidden = true;
			}

			if (payload.foundTarget && highlightComment(targetCommentId)) {
				return;
			}
		}

		highlightComment(targetCommentId);
	};

	const copyToClipboard = async (url) => {
		if (!url) {
			showToast(config.strings?.shareFailed || 'Unable to prepare a share link right now.', 'error');
			return;
		}

		try {
			await navigator.clipboard.writeText(url);
			showToast(config.strings?.shareCopied || 'Comment link copied to clipboard.', 'success');
		} catch (error) {
			await openAlertModal({
				title: config.strings?.shareManualTitle || 'Copy link manually',
				message: url,
				confirmText: config.strings?.dialogConfirm || 'Okay',
				confirm: false,
			});
		}
	};

	const replaceCommentHtml = (commentId, html) => {
		if (!commentId || !html) {
			return;
		}

		const current = root.querySelector(`#kosher-comment-${commentId}`);

		if (current) {
			current.querySelectorAll('.kosher-comments-edit-form, .kosher-comments-reply-form').forEach((form) => removeMiniEditor(form));
			current.outerHTML = html;
			bindPhotoTriggers(root);
		}
	};

	const bindPhotoTriggers = (scope) => {
		if (!scope) {
			return;
		}

		scope.querySelectorAll('[data-open-photo-modal]:not([data-photo-bound])').forEach((button) => {
			button.dataset.photoBound = '1';
			button.addEventListener('click', (event) => {
				event.preventDefault();
				event.stopPropagation();
				openPhotoModal(button);
			});
		});

		scope.querySelectorAll('.kosher-comments-open-all-photos:not([data-photo-bound])').forEach((button) => {
			button.dataset.photoBound = '1';
			button.addEventListener('click', (event) => {
				event.preventDefault();
				event.stopPropagation();
				const firstPhoto = root.querySelector('.kosher-comments-photo-strip [data-open-photo-modal]');

				if (firstPhoto) {
					openPhotoModal(firstPhoto);
				}
			});
		});
	};

	root.addEventListener('change', (event) => {
		const target = event.target;

		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (target.matches('input[type="file"]')) {
			updateFileLabel(target.closest('form'));
			return;
		}

		if (target.matches('.kosher-comments-report-form textarea[name="reason"]')) {
			updateReportCount();
			return;
		}
	});

	root.addEventListener('input', (event) => {
		const target = event.target;

		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (target.matches('.kosher-comments-report-form textarea[name="reason"]')) {
			updateReportCount();
		}
	});

	root.addEventListener('click', async (event) => {
		const target = event.target;

		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (target.closest('[data-edit-user-rating]')) {
			openEditRatingModal();
			return;
		}

		if (target.closest('[data-edit-rating-close]')) {
			closeEditRatingModal();
			return;
		}

		if (target.closest('[data-edit-rating-save]')) {
			const rating = editRatingPicker?.querySelector('input[name="rating"]')?.value || '';

			if (!rating) {
				if (editRatingPicker) {
					editRatingPicker.classList.add('is-emphasized');
					window.setTimeout(() => editRatingPicker.classList.remove('is-emphasized'), 1200);
				}
				return;
			}

			const formData = new FormData();
			formData.append('action', 'kosher_comments_update_rating');
			formData.append('nonce', config.nonce || '');
			formData.append('post_id', root.dataset.postId || '');
			formData.append('rating', rating);

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				});
				const result = await response.json();

				if (!result.success) {
					showToast(result.data?.message || config.strings?.editError || 'Unable to update the comment.', 'error');
					return;
				}

				applyUserRatedState(result.data?.rating || rating);
				replaceCommentHtml(result.data?.commentId, result.data?.html);
				updateRatingSummary(result.data?.summary);
				closeEditRatingModal();
				showToast(result.data?.message || 'Rating updated.', 'success');
			} catch (error) {
				showToast(config.strings?.networkError || 'The network request failed. Please try again.', 'error');
			}
			return;
		}

		const ratingOnlyButton = target.closest('[data-rating-only-submit]');

		if (ratingOnlyButton) {
			const form = ratingOnlyButton.closest('.kosher-comments-form');
			const picker = form?.querySelector('[data-rating-picker]');
			const rating = form?.querySelector('[name="rating"]')?.value || '';

			if (!form || form.dataset.busy === '1') {
				return;
			}

			if (!rating) {
				if (picker) {
					picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
					picker.classList.add('is-emphasized');
					window.setTimeout(() => picker.classList.remove('is-emphasized'), 1200);
				}
				return;
			}

			await postForm(form, { ratingOnly: true });
			return;
		}

		const ratingButton = target.closest('[data-rating]');

		if (ratingButton) {
			const picker = ratingButton.closest('[data-rating-picker]');

			if (picker) {
				setRating(picker, ratingButton.dataset.rating || 0);
			}
			return;
		}

		if (target.closest('[data-modal-close]')) {
			closeRatingModal();
			return;
		}

		if (target.closest('[data-report-close]')) {
			closeReportModal();
			return;
		}

		if (target.closest('[data-alert-close]')) {
			closeAlertModal(false);
			return;
		}

		if (target.closest('[data-photo-close]')) {
			closePhotoModal();
			return;
		}

		if (target.closest('[data-alert-confirm]')) {
			closeAlertModal(true);
			return;
		}

		if (target.closest('[data-alert-cancel]')) {
			closeAlertModal(false);
			return;
		}

		const ratingChoice = target.closest('[data-rating-choice]');

		if (ratingChoice) {
			if (ratingChoice.dataset.ratingChoice === 'yes' && pendingRatingForm) {
				const modalRating = modalRatingPicker?.querySelector('input[name="rating"]')?.value || '';
				const picker = pendingRatingForm.querySelector('[data-rating-picker]');

				if (!modalRating) {
					if (modalRatingPicker) {
						modalRatingPicker.classList.add('is-emphasized');
						window.setTimeout(() => modalRatingPicker.classList.remove('is-emphasized'), 1200);
					}
					return;
				}

				if (picker) {
					setRating(picker, modalRating);
				}

				pendingRatingForm.dataset.skipPrompt = '1';
				closeRatingModal();
				pendingRatingForm.requestSubmit();
			}

			if (ratingChoice.dataset.ratingChoice === 'no' && pendingRatingForm) {
				pendingRatingForm.dataset.skipPrompt = '1';
				closeRatingModal();
				pendingRatingForm.requestSubmit();
			}
			return;
		}

		if (jumpButton && target.closest('.kosher-comments-jump-form')) {
			mainForm?.scrollIntoView({ behavior: 'smooth', block: 'center' });
			focusFormCommentField(mainForm);
			return;
		}

		const replyToggle = target.closest('.kosher-comments-reply-toggle');

		if (replyToggle) {
			const comment = replyToggle.closest('.kosher-comment');
			const form = comment?.querySelector('.kosher-comments-reply-form');

			if (form) {
				form.hidden = !form.hidden;

				if (!form.hidden) {
					ensureMiniEditor(form);
					window.setTimeout(() => focusFormCommentField(form), 20);
				}
			} else {
				await openAlertModal({
					title: config.strings?.loginTitle || 'Login required',
					message: config.strings?.loginRequired || 'Please log in to interact with comments.',
					confirm: false,
				});
			}
			return;
		}

		if (target.closest('.kosher-comments-cancel-reply')) {
			const form = target.closest('.kosher-comments-reply-form');

			if (form) {
				form.hidden = true;
				resetForm(form);
			}
			return;
		}

		if (target.closest('.kosher-comments-toggle-replies')) {
			const button = target.closest('.kosher-comments-toggle-replies');
			const commentId = button.dataset.toggleReplies || '';
			const replies = root.querySelector(`[data-replies-container="${commentId}"]`);

			if (replies) {
				replies.hidden = !replies.hidden;
				button.textContent = replies.hidden ? 'Show Replies' : 'Hide Replies';
			}
			return;
		}

		const shareButton = target.closest('.kosher-comments-share');

		if (shareButton) {
			await copyToClipboard(shareButton.dataset.shareUrl || '');
			return;
		}

		const reportButton = target.closest('.kosher-comments-report-button');

		if (reportButton) {
			openReportModal({
				reportType: reportButton.dataset.reportType || 'comment',
				commentId: reportButton.dataset.commentId || '',
				imageId: reportButton.dataset.imageId || '',
			});
			return;
		}

		const editButton = target.closest('[data-edit-comment]');

		if (editButton) {
			const comment = editButton.closest('.kosher-comment');
			const form = comment?.querySelector('.kosher-comments-edit-form');

			if (form) {
				form.hidden = !form.hidden;

				if (!form.hidden) {
					ensureMiniEditor(form);
					window.setTimeout(() => focusFormCommentField(form), 20);
				}
			}
			return;
		}

		if (target.closest('.kosher-comments-cancel-edit')) {
			const form = target.closest('.kosher-comments-edit-form');

			if (form) {
				form.hidden = true;
			}
			return;
		}

		const deleteButton = target.closest('[data-delete-comment]');

		if (deleteButton) {
			const commentId = deleteButton.dataset.deleteComment || '';
			const confirmed = await openAlertModal({
				title: config.strings?.deleteTitle || 'Delete comment?',
				message: config.strings?.deleteConfirm || 'Delete this comment?',
				confirmText: config.strings?.deleteAction || 'Delete',
				cancelText: config.strings?.dialogCancel || 'Cancel',
				confirm: true,
			});

			if (!confirmed) {
				return;
			}

			const formData = new FormData();
			formData.append('action', 'kosher_comments_delete_comment');
			formData.append('nonce', config.nonce || '');
			formData.append('comment_id', commentId);

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				});
				const result = await response.json();

				if (!result.success) {
					showToast(result.data?.message || config.strings?.deleteError || 'Unable to delete the comment.', 'error');
					return;
				}

				const comment = root.querySelector(`#kosher-comment-${commentId}`);

				if (comment) {
					comment.querySelectorAll('.kosher-comments-edit-form, .kosher-comments-reply-form').forEach((form) => removeMiniEditor(form));
					comment.remove();
				}

				showFeedback(result.data?.message || config.strings?.deleteSuccess || 'Comment deleted.', 'success');
				showToast(result.data?.message || config.strings?.deleteSuccess || 'Comment deleted.', 'success');
			} catch (error) {
				showToast(config.strings?.deleteError || 'Unable to delete the comment.', 'error');
			}
			return;
		}

		const voteButton = target.closest('.kosher-comments-vote');

		if (voteButton) {
			const formData = new FormData();
			formData.append('action', 'kosher_comments_vote_comment');
			formData.append('nonce', config.nonce || '');
			formData.append('comment_id', voteButton.dataset.commentId || '');
			formData.append('vote_type', voteButton.dataset.voteType || '');

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				});
				const result = await response.json();

				if (!result.success) {
					showToast(result.data?.message || config.strings?.loginRequired || 'Please log in to interact with comments.', 'error');
					return;
				}

				const voteGroup = voteButton.closest('.kosher-comment-votes');

				if (voteGroup) {
					const likeButton = voteGroup.querySelector('.kosher-comments-vote[data-vote-type="like"]');
					const dislikeButton = voteGroup.querySelector('.kosher-comments-vote[data-vote-type="dislike"]');

					if (likeButton?.querySelector('.kosher-comments-vote-count')) {
						likeButton.querySelector('.kosher-comments-vote-count').textContent = String(result.data.likeCount || 0);
					}

					if (dislikeButton?.querySelector('.kosher-comments-vote-count')) {
						dislikeButton.querySelector('.kosher-comments-vote-count').textContent = String(result.data.dislikeCount || 0);
					}

					voteGroup.querySelectorAll('.kosher-comments-vote').forEach((button) => {
						updateVoteButtonState(button, button.dataset.voteType === result.data.activeVote);
					});
				}
			} catch (error) {
				showToast(config.strings?.networkError || 'The network request failed. Please try again.', 'error');
			}
			return;
		}

		if (target.closest('.kosher-comments-load-more') && loadMoreButton) {
			loadMoreButton.disabled = true;
			loadMoreButton.textContent = config.strings?.loadingMore || 'Loading...';
			const nextPage = getCurrentPage() + 1;
			const payload = await loadComments(nextPage);
			loadMoreButton.disabled = false;
			loadMoreButton.textContent = config.strings?.loadMore || 'Load more comments';

			if (!payload) {
				return;
			}

			if (list) {
				list.insertAdjacentHTML('beforeend', payload.html || '');
				bindPhotoTriggers(list);
			}

			setCurrentPage(payload.page || nextPage);

			if (!payload.hasMore) {
				loadMoreButton.hidden = true;
			}

			if (payload.foundTarget) {
				highlightComment(root.dataset.targetCommentId || '');
			}
			return;
		}

		const photoThumb = target.closest('[data-photo-thumb-index]');

		if (photoThumb) {
			activePhotoIndex = Number(photoThumb.dataset.photoThumbIndex || 0);
			renderPhotoModal();
			return;
		}

		const photoNav = target.closest('[data-photo-nav]');

		if (photoNav) {
			movePhotoModal(photoNav.dataset.photoNav === 'prev' ? -1 : 1);
		}
	});

	root.addEventListener('submit', async (event) => {
		const form = event.target;

		if (form instanceof HTMLFormElement && form.classList.contains('kosher-comments-form')) {
			event.preventDefault();

			if (form.dataset.busy === '1') {
				return;
			}

			const parentId = Number(form.dataset.parentId || 0);
			const rating = form.querySelector('[name="rating"]')?.value || '';
			const commentText = getFormCommentValue(form).replace(/<[^>]*>/g, '').trim();

			if (parentId === 0 && root.dataset.userHasRated !== '1' && !rating && form.dataset.skipPrompt !== '1') {
				pendingRatingForm = form;
				openRatingModal();
				return;
			}

			form.dataset.skipPrompt = '';
			await postForm(form, { ratingOnly: parentId === 0 && !!rating && !commentText });
			return;
		}

		if (form instanceof HTMLFormElement && form.classList.contains('kosher-comments-edit-form')) {
			event.preventDefault();

			const comment = form.closest('.kosher-comment');
			const commentId = comment?.dataset.commentId || '';
			const formData = new FormData();

			formData.append('action', 'kosher_comments_edit_comment');
			formData.append('nonce', config.nonce || '');
			formData.append('comment_id', commentId);
			formData.append('comment_text', getFormCommentValue(form));

			const rating = form.querySelector('[name="rating"]')?.value || '';

			if (rating) {
				formData.append('rating', rating);
			}

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				});
				const result = await response.json();

				if (!result.success) {
					showToast(result.data?.message || config.strings?.editError || 'Unable to update the comment.', 'error');
					return;
				}

				if (result.data?.userRating) {
					applyUserRatedState(result.data.userRating);
				}

				replaceCommentHtml(result.data.commentId, result.data.html);
				updateRatingSummary(result.data?.summary);
				showFeedback(result.data?.message || config.strings?.editSuccess || 'Comment updated.', 'success');
				showToast(result.data?.message || config.strings?.editSuccess || 'Comment updated.', 'success');
			} catch (error) {
				showToast(config.strings?.editError || 'Unable to update the comment.', 'error');
			}
			return;
		}

		if (form instanceof HTMLFormElement && form.classList.contains('kosher-comments-report-form')) {
			event.preventDefault();

			const reasonField = form.querySelector('[name="reason"]');

			if (reasonField && reasonField.value.length > 140) {
				showToast(config.strings?.reportTooLong || 'Report comments must be 140 characters or less.', 'error');
				return;
			}

			const formData = new FormData(form);
			formData.append('action', 'kosher_comments_report_item');
			formData.append('nonce', config.nonce || '');

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				});
				const result = await response.json();

				if (!result.success) {
					showToast(result.data?.message || config.strings?.reportError || 'Unable to send the report.', 'error');
					return;
				}

				closeReportModal();
				showFeedback(result.data?.message || config.strings?.reportSuccess || 'Thanks. Your report was sent.', 'success');
				showToast(result.data?.message || config.strings?.reportSuccess || 'Thanks. Your report was sent.', 'success');
			} catch (error) {
				showToast(config.strings?.reportError || 'Unable to send the report.', 'error');
			}
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			if (photoModal && !photoModal.hidden) {
				closePhotoModal();
				return;
			}

			if (reportModal && !reportModal.hidden) {
				closeReportModal();
				return;
			}

			if (alertModal && !alertModal.hidden) {
				closeAlertModal(false);
				return;
			}

			if (ratingModal && !ratingModal.hidden) {
				closeRatingModal();
			}
		}

		if ((event.key === 'ArrowLeft' || event.key === 'ArrowRight') && photoModal && !photoModal.hidden) {
			movePhotoModal(event.key === 'ArrowLeft' ? -1 : 1);
		}
	});

	updateReportCount();
	bindPhotoTriggers(root);
	ensureSharedCommentVisible();
})();
