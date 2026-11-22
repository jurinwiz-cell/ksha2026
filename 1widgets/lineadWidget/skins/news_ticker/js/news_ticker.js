/**
 * @brief 뉴스티커 형식으로 표시하기 위한 초기화 작업
 **/

var newsTickerMoveUpStep = new Array();
function doStartTickerScroll(id, itemHeight, speed, scrollRate, stop) {
	var stop = stop=='Y'?true:false;
	(function($){
		var pObj = $(id);
		var obj = id.replace('#','');
		var childObj = $(id + ' ul:first-child');

		while(childObj) {
			childObj.attr('id', obj+'_first');
			var cloneObj = childObj.clone(true);
			cloneObj.attr('id', obj+'_second');
			cloneObj.insertBefore(childObj);

			var ticker = {'pObj':pObj, 'child':childObj, 'itemHeight':itemHeight, 'speed':speed, 'scrollRate':scrollRate}

			newsTickerMoveUpStep[obj] = 1;

			// 마우스 올리면 스크롤 멈추기
			if(stop) {
				pObj.hover(function() {
					newsTickerMoveUpStep[obj] = 0;
				}, function(){
					newsTickerMoveUpStep[obj] = 1;
				});
			}

			doTickerScroll(ticker);
			return;
		}
	})(jQuery);
}
function doTickerScroll(obj) {
	if (typeof(obj.itemHeight) == 'undefined' || !obj.itemHeight) return;
	var scrollRate = parseInt(obj.scrollRate)?parseInt(obj.scrollRate):3500;
	var itemHeight = parseInt(obj.itemHeight)?parseInt(obj.itemHeight):25;
	var pObj = obj.pObj;
	var child = obj.child;
	var st = pObj.scrollTop();

	st += newsTickerMoveUpStep[pObj.attr('id')];

	if(st > child.height()) st = 0;
	pObj.scrollTop(st);

	if(pObj.scrollTop() % itemHeight == 0) setTimeout( function() { doTickerScroll(obj); }, scrollRate);
	else setTimeout( function() { doTickerScroll(obj); }, obj.speed);
}