<?php

class Popup_ManagerAdminController extends Popup_Manager
{
	function init()
	{
	}

	/**
	 * Insert a new popup
	 */
	function procPopup_managerAdminInsertPopup()
	{
		$vars = Context::getRequestVars();

		// Generate popup_srl if not provided
		if (!$vars->popup_srl)
		{
			$vars->popup_srl = getNextSequence();
		}

		// Get logged-in member
		$logged_info = Context::get('logged_info');
		$vars->member_srl = $logged_info->member_srl ?? 0;

		// Convert dates (with optional time) to DB format (YmdHis) or set to empty
		$vars->start_date = $this->buildDateTime($vars->start_date, $vars->start_time, '000000');
		$vars->end_date = $this->buildDateTime($vars->end_date, $vars->end_time, '235959');

		// Normalize target type
		$vars->target_type = ($vars->target_type === 'exclude') ? 'exclude' : 'include';

		// Handle image upload for image type popup
		if ($vars->popup_type === 'image')
		{
			$image_url = $this->handleImageUpload($vars->popup_srl);
			if ($image_url)
			{
				$vars->image_url = $image_url;
			}
		}

		// Execute insert query
		$output = executeQuery('popup_manager.insertPopup', $vars);
		if (!$output->toBool())
		{
			return $output;
		}

		// Handle target modules
		$this->saveTargetModules($vars->popup_srl, $vars->target_modules);

		$this->add('popup_srl', $vars->popup_srl);
		$this->setMessage('msg_popup_inserted');

		$returnUrl = Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopup_managerAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * Update an existing popup
	 */
	function procPopup_managerAdminUpdatePopup()
	{
		$vars = Context::getRequestVars();

		if (!$vars->popup_srl)
		{
			return new BaseObject(-1, 'msg_invalid_request');
		}

		// Convert dates (with optional time) to DB format (YmdHis) or set to empty
		$vars->start_date = $this->buildDateTime($vars->start_date, $vars->start_time, '000000');
		$vars->end_date = $this->buildDateTime($vars->end_date, $vars->end_time, '235959');

		// Normalize target type
		$vars->target_type = ($vars->target_type === 'exclude') ? 'exclude' : 'include';

		// Handle image upload for image type popup
		if ($vars->popup_type === 'image')
		{
			$new_image_url = $this->handleImageUpload($vars->popup_srl);
			if ($new_image_url)
			{
				// Delete old image if exists
				if ($vars->current_image_url)
				{
					$this->deletePopupImage($vars->current_image_url);
				}
				$vars->image_url = $new_image_url;
			}
			elseif ($vars->current_image_url)
			{
				// Keep existing image if no new upload
				$vars->image_url = $vars->current_image_url;
			}
		}
		else
		{
			// If switching to HTML type, clear image fields
			$vars->image_url = '';
			$vars->image_link = '';
		}

		// Execute update query
		$output = executeQuery('popup_manager.updatePopup', $vars);
		if (!$output->toBool())
		{
			return $output;
		}

		// Handle target modules
		$this->saveTargetModules($vars->popup_srl, $vars->target_modules);

		$this->setMessage('msg_popup_updated');

		$returnUrl = Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopup_managerAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * Delete a popup
	 */
	function procPopup_managerAdminDeletePopup()
	{
		$popup_srl = Context::get('popup_srl');

		if (!$popup_srl)
		{
			return new BaseObject(-1, 'msg_invalid_request');
		}

		// Get popup to delete its image
		$oModel = getAdminModel('popup_manager');
		$popup = $oModel->getPopup($popup_srl);
		if ($popup && $popup->image_url)
		{
			$this->deletePopupImage($popup->image_url);
		}

		$args = new stdClass();
		$args->popup_srl = $popup_srl;

		// Delete popup
		$output = executeQuery('popup_manager.deletePopup', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		// Delete target modules
		executeQuery('popup_manager.deleteTargetModules', $args);

		$this->setMessage('msg_popup_deleted');

		$returnUrl = Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopup_managerAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * Delete multiple popups
	 */
	function procPopup_managerAdminDeletePopups()
	{
		$popup_srls = Context::get('popup_srls');

		if (!$popup_srls)
		{
			return new BaseObject(-1, 'msg_select_popup');
		}

		if (!is_array($popup_srls))
		{
			$popup_srls = explode(',', $popup_srls);
		}

		$oModel = getAdminModel('popup_manager');
		$args = new stdClass();
		foreach ($popup_srls as $popup_srl)
		{
			$popup_srl = trim($popup_srl);

			// Get popup to delete its image
			$popup = $oModel->getPopup($popup_srl);
			if ($popup && $popup->image_url)
			{
				$this->deletePopupImage($popup->image_url);
			}

			$args->popup_srl = $popup_srl;
			executeQuery('popup_manager.deletePopup', $args);
			executeQuery('popup_manager.deleteTargetModules', $args);
		}

		$this->setMessage('msg_popup_deleted');

		$returnUrl = Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopup_managerAdminList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * Toggle popup enabled/disabled status
	 */
	function procPopup_managerAdminToggleStatus()
	{
		$popup_srl = Context::get('popup_srl');

		// Get current popup
		$oModel = getAdminModel('popup_manager');
		$popup = $oModel->getPopup($popup_srl);

		if (!$popup)
		{
			return new BaseObject(-1, 'msg_invalid_request');
		}

		// Toggle status
		$args = new stdClass();
		$args->popup_srl = $popup_srl;
		$args->is_enabled = ($popup->is_enabled === 'Y') ? 'N' : 'Y';

		$output = executeQuery('popup_manager.updatePopup', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('msg_popup_status_changed');
	}

	/**
	 * Combine a date (Y-m-d) and an optional time (H:i) into DB format (YmdHis).
	 * Returns '' when no date is given. Uses $default_time (HHMMSS) when the time is empty.
	 */
	private function buildDateTime($date, $time, $default_time)
	{
		$date = trim((string)$date);
		if ($date === '')
		{
			return '';
		}

		// Normalize date part to Ymd
		$ymd = preg_replace('/[^0-9]/', '', $date);
		if (strlen($ymd) < 8)
		{
			return '';
		}
		$ymd = substr($ymd, 0, 8);

		// Normalize time part to His
		$time = preg_replace('/[^0-9]/', '', (string)$time);
		if ($time === '')
		{
			$his = $default_time;
		}
		else
		{
			$his = str_pad(substr($time, 0, 6), 6, '0', STR_PAD_RIGHT);
		}

		return $ymd . $his;
	}

	/**
	 * Handle image upload
	 */
	private function handleImageUpload($popup_srl)
	{
		// Check if file was uploaded
		if (!isset($_FILES['image_file']))
		{
			return null;
		}

		$file = $_FILES['image_file'];

		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			$error_messages = [
				UPLOAD_ERR_INI_SIZE => '파일이 php.ini의 upload_max_filesize를 초과했습니다.',
				UPLOAD_ERR_FORM_SIZE => '파일이 폼에서 지정한 MAX_FILE_SIZE를 초과했습니다.',
				UPLOAD_ERR_PARTIAL => '파일이 일부만 업로드되었습니다.',
				UPLOAD_ERR_NO_FILE => '파일이 업로드되지 않았습니다.',
				UPLOAD_ERR_NO_TMP_DIR => '임시 폴더가 없습니다.',
				UPLOAD_ERR_CANT_WRITE => '디스크에 파일을 쓸 수 없습니다.',
				UPLOAD_ERR_EXTENSION => 'PHP 확장에 의해 업로드가 중단되었습니다.',
			];
			$error_msg = $error_messages[$file['error']] ?? '알 수 없는 업로드 오류';
			$this->setMessage($error_msg);
			return null;
		}

		// Check for empty file
		if (empty($file['tmp_name']) || $file['size'] == 0)
		{
			return null;
		}

		// Validate file type
		$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);

		if (!in_array($mime_type, $allowed_types))
		{
			return null;
		}

		// Create upload directory
		$upload_dir = RX_BASEDIR . 'files/popup_manager/' . date('Ym') . '/';
		if (!is_dir($upload_dir))
		{
			FileHandler::makeDir($upload_dir);
		}

		// Generate unique filename
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!$ext)
		{
			// Fallback extension based on mime type
			$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
			$ext = $ext_map[$mime_type] ?? 'jpg';
		}
		$filename = $popup_srl . '_' . time() . '.' . $ext;
		$target_path = $upload_dir . $filename;

		// Move uploaded file
		if (move_uploaded_file($file['tmp_name'], $target_path))
		{
			return './files/popup_manager/' . date('Ym') . '/' . $filename;
		}

		return null;
	}

	/**
	 * Delete popup image file
	 */
	private function deletePopupImage($image_url)
	{
		if (!$image_url)
		{
			return;
		}

		$file_path = RX_BASEDIR . ltrim($image_url, './');
		if (file_exists($file_path))
		{
			@unlink($file_path);
		}
	}

	/**
	 * Save target modules for a popup
	 */
	private function saveTargetModules($popup_srl, $target_modules)
	{
		// Delete existing
		$args = new stdClass();
		$args->popup_srl = $popup_srl;
		executeQuery('popup_manager.deleteTargetModules', $args);

		// Insert new ones
		if (!empty($target_modules) && is_array($target_modules))
		{
			foreach ($target_modules as $module_srl)
			{
				if (!$module_srl)
				{
					continue;
				}
				$args = new stdClass();
				$args->popup_srl = $popup_srl;
				$args->module_srl = $module_srl;
				executeQuery('popup_manager.insertTargetModule', $args);
			}
		}
	}
}
