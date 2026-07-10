function accessibility__report_find(event) {
	let obj = (event.target.matches('.picker--clickable')) ? event.target : event.target.closest('.picker--clickable');
	if (!obj || !obj.hasAttribute('data-selector')) return false;
	if (!obj.hasAttribute('data-onthispage')) {
		location.href = fiCMS.host+'/'+obj.getAttribute('data-path');
		return true;
	}

	let selector = obj.getAttribute('data-selector');
	let target = document.querySelector(selector);
	if (!target) return false;
	let style = getComputedStyle(target);
	if (style.display === 'none') target = target.parentNode;
	helper__scroll_to(target,500);
	target.removeClassOnAnimationEnd('highlight');
	target.focus();
}

function accessibility__report_check(obj,json) {
	let selector = json.attributes['data-selector'];
	if (!selector) return false;
	obj.classList.add('picker--clickable');
	try {
		let elem = document.querySelector(selector);
		if (elem) obj.setAttribute('data-onthispage','true');
	} catch (e) {
		console.log(e);
	}
}
