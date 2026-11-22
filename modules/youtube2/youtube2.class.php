<?php
class youtube2 extends ModuleObject {
	public function moduleInstall() {
		return new BaseObject();
	}

	public function checkUpdate() {
		return false;
	}

	public function moduleUpdate() {
		return new BaseObject(0, 'success_updated');
	}

	public function moduleUninstall() {
		return new BaseObject();
	}
	
	public function recompileCache() {
	}
}
