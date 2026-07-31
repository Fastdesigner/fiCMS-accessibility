function accessibility__test_focus() {
	let el = document.createElement('a'), style = document.createElement('style');
	el.className = 'focus-test';
	el.href = fiCMS.host;
	el.style.cssText = 'position:absolute;top:-9999px;opacity:0;pointer-events:none';
	style.textContent = '.focus-test:focus {outline:none} .focus-test:focus-visible {outline:2px solid red}';
	document.body.appendChild(el);
	document.head.appendChild(style);

	el.focus({ preventScroll: true, focusVisible: true });
	let result = {forced:el.matches(':focus-visible')};
	el.blur();
	el.focus({ preventScroll: true, focusVisible: false });
	result.suppressed = !el.matches(':focus-visible');

	el.remove();
	style.remove();

	return result.forced && result.suppressed;
}

function accessibility__collect_motion_preference_rules(preference) {
	let collected = [], isPreference = condition => new RegExp('\\(\\s*prefers-reduced-motion\\s*:\\s*' + preference + '\\s*\\)','i').test(condition || '');
	let collectStyles = rules => Array.from(rules || []).forEach(rule => {
		if (rule.selectorText && rule.style) {
			let css = (rule.style.cssText || '').toLowerCase();
			if (!css) return;
			if (preference === 'reduce') {
				if (!/(^|;)\s*(animation|transition|scroll-behavior)\s*:/.test(css) && !/(^|;)\s*(animation-duration|transition-duration|animation-name|transition-property)\s*:/.test(css)) return;
				collected.push({selector:rule.selectorText,css:css});
				return;
			}

			let animation = /(^|;)\s*(animation|animation-name)\s*:/.test(css),
				transitionProperties = accessibility__transition_properties(rule.style).filter(accessibility__is_motion_property);
			if (animation || transitionProperties.length) collected.push({selector:rule.selectorText,css:css,animation:animation,transitionProperties:transitionProperties});
			return;
		}
		if (rule.cssRules) collectStyles(rule.cssRules);
	});
	let collect = rules => Array.from(rules || []).forEach(rule => {
		if (rule.type === CSSRule.MEDIA_RULE && isPreference(rule.conditionText)) collectStyles(rule.cssRules);
		else if (rule.cssRules) collect(rule.cssRules);
	});

	Array.from(document.styleSheets).forEach(sheet => {
		try { collect(sheet.cssRules); } catch(e) {}
	}); return collected;
}

function accessibility__collect_motion_animation_names() {
	let collected = new Set(), collect = rules => {
		Array.from(rules || []).forEach(rule => {
			if (rule.type === CSSRule.KEYFRAMES_RULE) {
				if (Array.from(rule.cssRules || []).some(frame => Array.from(frame.style || []).some(accessibility__is_motion_property))) collected.add(rule.name);
				return;
			}
			if (rule.cssRules) collect(rule.cssRules);
		});
	};

	Array.from(document.styleSheets).forEach(sheet => {
		try { collect(sheet.cssRules); } catch(e) {}
	}); return collected;
}

function accessibility__css_list(value) {
	let parts = [], current = '', depth = 0;
	for (let character of String(value || '')) {
		if (character === '(') depth++;
		else if (character === ')' && depth > 0) depth--;
		if (character === ',' && depth === 0) {
			if (current.trim()) parts.push(current.trim());
			current = '';
			continue;
		}
		current += character;
	}
	if (current.trim()) parts.push(current.trim());
	return parts;
}

function accessibility__css_tokens(value) {
	let tokens = [], current = '', depth = 0;
	for (let character of String(value || '')) {
		if (character === '(') depth++;
		else if (character === ')' && depth > 0) depth--;
		if (/\s/.test(character) && depth === 0) {
			if (current) tokens.push(current);
			current = '';
			continue;
		}
		current += character;
	}
	if (current) tokens.push(current);
	return tokens;
}

function accessibility__transition_properties(style) {
	let properties = accessibility__css_list(style.transitionProperty).map(value => value.toLowerCase());
	if (properties.length) return properties;
	return accessibility__css_list(style.transition).map(value => accessibility__css_tokens(value.toLowerCase()).find(token => {
		if (/^-?\d*\.?\d+(ms|s)$/.test(token)) return false;
		if (/^(ease|ease-in|ease-out|ease-in-out|linear|step-start|step-end|allow-discrete|normal)$/.test(token)) return false;
		return !/^(cubic-bezier|steps|linear|var|calc)\(/.test(token);
	}) || 'all');
}

function accessibility__css_time_values(value) {
	if (!value) return [];
	return String(value).toLowerCase().split(',').map(part => {
		let max = 0, match, re = /(-?\d*\.?\d+)\s*(ms|s)\b/g;
		while ((match = re.exec(part))) {
			let n = parseFloat(match[1]);
			if (!isNaN(n)) max = Math.max(max,(match[2] === 's') ? n * 1000 : n);
		}
		return max;
	});
}

function accessibility__css_time_max(value) {
	return Math.max(0,...accessibility__css_time_values(value));
}

function accessibility__selector_matches(el, selectorText) {
	return String(selectorText || '').split(',').some(selector => {
		selector = selector.trim().replace(/::[a-z-]+(\([^)]*\))?/ig,'').trim();
		if (selector === '') selector = '*';
		try { return el.matches(selector); } catch(e) { return false; }
	});
}

function accessibility__is_motion_property(property) {
	return [
		'transform','translate','scale','rotate','offset-distance','offset-rotate',
		'top','right','bottom','left','width','height','block-size','inline-size',
		'grid-template-columns','grid-template-rows'
	].includes(property) || /^(inset|margin|padding|min-|max-)/.test(property);
}

function accessibility__has_motion_animation(cs) {
	let names = String(cs.animationName || '').split(',').map(value => value.trim()),
		durations = accessibility__css_time_values(cs.animationDuration),
		motionNames = fiCMS.accessibility.motionAnimations || new Set();
	return names.some((name,index) => {
		if (!name || name === 'none' || !motionNames.has(name)) return false;
		return durations.length > 0 && durations[index % durations.length] > 150;
	});
}

function accessibility__motion_transition_properties(cs) {
	let properties = String(cs.transitionProperty || '').toLowerCase().split(',').map(value => value.trim()),
		durations = accessibility__css_time_values(cs.transitionDuration);
	return properties.filter((property,index) => durations.length && durations[index % durations.length] > 150 && accessibility__is_motion_property(property));
}

function accessibility__has_motion_transition(cs) {
	return accessibility__motion_transition_properties(cs).length > 0;
}

function accessibility__motion_preference_opt_in(el,type,cs) {
	let matches = (fiCMS.accessibility.motionOptIns || []).filter(rule => accessibility__selector_matches(el,rule.selector));
	if (type === 'animation') return matches.some(rule => rule.animation);
	let covered = new Set(matches.flatMap(rule => rule.transitionProperties || []));
	return accessibility__motion_transition_properties(cs).every(property => covered.has(property));
}

function accessibility__motion_description(cs,hasAnimation,hasTransition) {
	let descriptions = [];
	if (hasAnimation) descriptions.push('animation: ' + cs.animationName + ' ' + cs.animationDuration);
	if (hasTransition) {
		let properties = String(cs.transitionProperty || '').split(',').map(value => value.trim()), durations = String(cs.transitionDuration || '').split(',').map(value => value.trim());
		descriptions.push('transition: ' + properties.map((property,index) => ({property:property,duration:durations[index % durations.length] || '0s'})).filter(entry => accessibility__is_motion_property(entry.property)).map(entry => entry.property + ' ' + entry.duration).join(', '));
	}
	return descriptions.join('; ');
}

function accessibility__renderer_load() {
	if (window.modernScreenshot) return Promise.resolve(window.modernScreenshot);
	if (accessibility__renderer_load.promise) return accessibility__renderer_load.promise;
	accessibility__renderer_load.promise = new Promise((resolve,reject) => {
		let script = document.createElement('script');
		script.src = new URL('vendor/modern-screenshot.js',import.meta.url).href;
		script.onload = () => window.modernScreenshot ? resolve(window.modernScreenshot) : reject(new Error('Contrast renderer export fehlt'));
		script.onerror = () => reject(new Error('Contrast renderer konnte nicht geladen werden'));
		document.head.appendChild(script);
	});
	return accessibility__renderer_load.promise;
}

function accessibility__text_rects(el) {
	let rects = [];
	Array.from(el.childNodes).filter(node => node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()).forEach(node => {
		let range = document.createRange();
		range.selectNodeContents(node);
		rects.push(...Array.from(range.getClientRects()));
		range.detach();
	});
	return rects;
}

function accessibility__state_groups(elements) {
	let groups = [];
	elements.forEach(entry => {
		let group = groups.at(-1);
		if (!group || group.parents.length !== entry.parents.length || group.parents.some((parent,index) => parent !== entry.parents[index])) groups.push(group = {parents:entry.parents,elements:[]});
		group.elements.push(entry.obj);
	});
	return groups;
}

function accessibility__stage_create(group,stateMap) {
	let sourceRoot = group.parents.find(parent => stateMap.get(parent) === false);
	if (!sourceRoot) return {root:false,target:source => source,inView:callback => callback(),remove:() => {}};

	let cloneRoot = sourceRoot.cloneNode(true), sources = [sourceRoot,...sourceRoot.querySelectorAll('*')], clones = [cloneRoot,...cloneRoot.querySelectorAll('*')], targets = new WeakMap(),
		properties = ['position','inset','inline-size','translate','pointer-events','z-index','opacity'], styles = {};
	sources.forEach((source,index) => {
		let clone = clones[index];
		targets.set(source,clone);
		clone.style.setProperty('animation','none','important');
		clone.style.setProperty('transition','none','important');
		if (['INPUT','TEXTAREA','SELECT'].includes(source.tagName)) {
			clone.value = source.value;
			if ('checked' in source) clone.checked = source.checked;
			if ('selectedIndex' in source) clone.selectedIndex = source.selectedIndex;
		}
		if (source.tagName === 'CANVAS') {
			clone.width = source.width;
			clone.height = source.height;
			clone.getContext('2d').drawImage(source,0,0);
		}
	});
	group.parents.forEach(parent => {
		if (targets.has(parent)) targets.get(parent).open = true;
	});
	properties.forEach(property => styles[property] = {value:cloneRoot.style.getPropertyValue(property),priority:cloneRoot.style.getPropertyPriority(property)});
	let width = sourceRoot.tagName === 'DETAILS' ? sourceRoot.getBoundingClientRect().width : false,
		restore = () => properties.forEach(property => {
			cloneRoot.style.removeProperty(property);
			if (styles[property].value) cloneRoot.style.setProperty(property,styles[property].value,styles[property].priority);
		}),
		hide = () => {
			cloneRoot.style.setProperty('position','fixed','important');
			cloneRoot.style.setProperty('inset','0 auto auto 0','important');
			if (width > 0) cloneRoot.style.setProperty('inline-size',width + 'px','important');
			cloneRoot.style.setProperty('translate','-200vw 0','important');
			cloneRoot.style.setProperty('pointer-events','none','important');
			cloneRoot.style.setProperty('z-index','-1','important');
		};

	cloneRoot.setAttribute('data-ficms-accessibility-stage','');
	sourceRoot.after(cloneRoot);
	hide();
	return {
		root:cloneRoot,
		target:source => targets.get(source) || source,
		inView:callback => {
			restore();
			cloneRoot.style.setProperty('opacity','0','important');
			cloneRoot.style.setProperty('pointer-events','auto','important');
			cloneRoot.style.setProperty('z-index','2147483647','important');
			try { return callback(); }
			finally { restore(); hide(); }
		},
		remove:() => cloneRoot.remove()
	};
}

function accessibility__contrast_root(el) {
	let root = el, color = {r:0,g:0,b:0,alpha:0};
	for (let current = el; current; current = current.parentElement) {
		let cs = getComputedStyle(current), layer = helper__get_color_effective(current), opacity = parseFloat(cs.opacity);
		if (cs.backgroundImage !== 'none' || cs.mixBlendMode !== 'normal' || (!isNaN(opacity) && opacity > 0 && opacity < 1)) root = current;
		if (layer.rgba && layer.alpha > 0) color = helper__color_composite(color,layer);
		if (color.alpha >= 0.999) break;
	}
	if (root !== document.body) return root;
	return el.closest('dialog, details, section, header, footer, nav, aside, main > *') || el;
}

function accessibility__pixel(pixels,x,y) {
	let index = (y * pixels.width + x) * 4;
	return {r:pixels.data[index],g:pixels.data[index + 1],b:pixels.data[index + 2],alpha:pixels.data[index + 3] / 255};
}

function accessibility__mask_alpha(mask,background,x,y) {
	let painted = accessibility__pixel(mask,x,y), bg = accessibility__pixel(background,x,y), ratios = [], ink = {r:255,g:0,b:255};
	['r','g','b'].forEach(channel => {
		let distance = ink[channel] - bg[channel];
		if (Math.abs(distance) <= 32) return;
		let ratio = (painted[channel] - bg[channel]) / distance;
		if (ratio >= -0.05 && ratio <= 1.05) ratios.push(Math.max(0,Math.min(1,ratio)));
	});
	if (!ratios.length) return 0;
	ratios.sort((a,b) => a - b);
	return ratios[Math.floor(ratios.length / 2)];
}

function accessibility__contrast_pixels(record,painted,background,mask,rootRect) {
	let scaleX = painted.width / rootRect.width, scaleY = painted.height / rootRect.height, samples = [], fallback = [];
	record.rects.forEach(rect => {
		let left = Math.max(1,Math.floor((rect.left - rootRect.left) * scaleX)), right = Math.min(painted.width - 1,Math.ceil((rect.right - rootRect.left) * scaleX)),
			top = Math.max(1,Math.floor((rect.top - rootRect.top) * scaleY)), bottom = Math.min(painted.height - 1,Math.ceil((rect.bottom - rootRect.top) * scaleY));
		for (let y = top; y < bottom; y++) for (let x = left; x < right; x++) {
			let alpha = accessibility__mask_alpha(mask,background,x,y);
			if (alpha <= 0.05) continue;
			fallback.push({x:x,y:y,alpha:alpha});
		}
	});

	if (fallback.length) {
		let maxAlpha = Math.max(...fallback.map(sample => sample.alpha));
		samples = fallback.filter(sample => sample.alpha >= maxAlpha - 0.01);
	}
	let result = {contrast:Infinity,color:'',background:''}, hasOpacity = record.colors.complex.some(value => value.endsWith(': opacity'));
	samples.forEach(sample => {
		let bg = accessibility__pixel(background,sample.x,sample.y), paint = accessibility__pixel(painted,sample.x,sample.y),
			color = record.foreground.actualType === 'color' && !hasOpacity ? helper__color_composite(record.foreground,bg) : paint,
			backgroundColor = `rgb(${bg.r},${bg.g},${bg.b})`;
		if (record.foreground.actualType !== 'color' && !hasOpacity && sample.alpha < 0.98) color = {
			r:Math.max(0,Math.min(255,Math.round((paint.r - (1 - sample.alpha) * bg.r) / sample.alpha))),
			g:Math.max(0,Math.min(255,Math.round((paint.g - (1 - sample.alpha) * bg.g) / sample.alpha))),
			b:Math.max(0,Math.min(255,Math.round((paint.b - (1 - sample.alpha) * bg.b) / sample.alpha)))
		};
		let contrast = helper__get_contrast(color.rgba || `rgb(${color.r},${color.g},${color.b})`,backgroundColor);
		if (contrast >= result.contrast) return;
		result = {contrast:contrast,color:color.rgba || `rgb(${color.r},${color.g},${color.b})`,background:backgroundColor};
	});
	if (Number.isFinite(result.contrast)) return result;

	let rect = record.rects[0], x = Math.max(0,Math.min(background.width - 1,Math.round((rect.left + rect.width / 2 - rootRect.left) * scaleX))),
		y = Math.max(0,Math.min(background.height - 1,Math.round((rect.top + rect.height / 2 - rootRect.top) * scaleY))), bg = accessibility__pixel(background,x,y),
		backgroundColor = `rgb(${bg.r},${bg.g},${bg.b})`, color = record.foreground.rgba ? helper__color_composite(record.foreground,bg).rgba : backgroundColor;
	return {contrast:helper__get_contrast(color,backgroundColor),color:color,background:backgroundColor};
}

function accessibility__contrast_clone(cloned,mode) {
	if (cloned.nodeType !== Node.ELEMENT_NODE) return;
	cloned.style.setProperty('animation','none','important');
	cloned.style.setProperty('transition','none','important');
	if (mode === 'painted' || !cloned.hasAttribute('data-ficms-contrast-capture')) return;
	let color = mode === 'mask' ? 'rgb(255,0,255)' : 'transparent';
	cloned.style.setProperty('color',color,'important');
	cloned.style.setProperty('-webkit-text-fill-color',color,'important');
	cloned.style.setProperty('text-shadow','none','important');
	if (cloned.hasAttribute('data-ficms-contrast-text-paint')) cloned.style.setProperty('background-image','none','important');
}

async function accessibility__contrast_canvas(renderer,root,rootRect,background,mode) {
	let timeout;
	try {
		return await Promise.race([
			renderer.domToCanvas(root,{
				width:rootRect.width,
				height:rootRect.height,
				scale:1,
				backgroundColor:background,
				style:root.hasAttribute('data-ficms-accessibility-stage') ? {translate:'none'} : null,
				timeout:5000,
				font:false,
				onCloneEachNode:cloned => accessibility__contrast_clone(cloned,mode)
			}),
			new Promise((resolve,reject) => timeout = setTimeout(() => reject(new Error('Contrast capture timeout')),8000))
		]);
	} finally {
		clearTimeout(timeout);
	}
}

async function accessibility__contrast_render(renderer,root,targets) {
	let rootRect = root.getBoundingClientRect(), background = root.parentElement ? helper__get_background_color(root.parentElement).rgba : 'rgb(255,255,255)';
	if (rootRect.width <= 0 || rootRect.height <= 0) return;
	targets.forEach(record => {
		record.el.setAttribute('data-ficms-contrast-capture','');
		if (record.foreground.actualType !== 'color') record.el.setAttribute('data-ficms-contrast-text-paint','');
	});
	try {
		let painted = await accessibility__contrast_canvas(renderer,root,rootRect,background,'painted'), empty = await accessibility__contrast_canvas(renderer,root,rootRect,background,'empty'),
			mask = await accessibility__contrast_canvas(renderer,root,rootRect,background,'mask'), paintedContext = painted.getContext('2d',{willReadFrequently:true}),
			emptyContext = empty.getContext('2d',{willReadFrequently:true}), maskContext = mask.getContext('2d',{willReadFrequently:true}),
			paintedPixels = {data:paintedContext.getImageData(0,0,painted.width,painted.height).data,width:painted.width,height:painted.height},
			emptyPixels = {data:emptyContext.getImageData(0,0,empty.width,empty.height).data,width:empty.width,height:empty.height},
			maskPixels = {data:maskContext.getImageData(0,0,mask.width,mask.height).data,width:mask.width,height:mask.height};
		targets.forEach(record => fiCMS.accessibility.contrastPixels.set(record.source,accessibility__contrast_pixels(record,paintedPixels,emptyPixels,maskPixels,rootRect)));
	} finally {
		targets.forEach(record => {
			record.el.removeAttribute('data-ficms-contrast-capture');
			record.el.removeAttribute('data-ficms-contrast-text-paint');
		});
	}
}

async function accessibility__contrast_capture(elements,states) {
	fiCMS.accessibility.contrastColors = new WeakMap();
	fiCMS.accessibility.contrastPixels = new WeakMap();
	let stateMap = new WeakMap(states.map(({el,wasOpen}) => [el,wasOpen])), renderer;
	for (let group of accessibility__state_groups(elements)) {
		let stage = accessibility__stage_create(group,stateMap), roots = new Map();
		try {
			group.elements.forEach(source => {
				if (!Array.from(source.childNodes).some(node => node.nodeType === Node.TEXT_NODE && node.nodeValue.trim())) return;
				let obj = stage.target(source), colors = helper__get_contrast_colors(obj), rects = accessibility__text_rects(obj);
				if (!rects.length || (typeof obj.checkVisibility === 'function' && !obj.checkVisibility({checkOpacity:true,checkVisibilityCSS:true}))) return;
				fiCMS.accessibility.contrastColors.set(source,colors);
				if (!colors.complex.length) return;
				let root = stage.root || accessibility__contrast_root(obj);
				if (!roots.has(root)) roots.set(root,[]);
				roots.get(root).push({el:obj,source:source,rects:rects,colors:colors,foreground:helper__get_color_effective(obj,'color')});
			});
			if (roots.size && !renderer) renderer = await accessibility__renderer_load();
			for (let [root,targets] of roots) await accessibility__contrast_render(renderer,root,targets);
		} finally {
			stage.remove();
		}
	}
}

function accessibility__publish_result(result) {
	if (window.accessibility__show_dialog) window.accessibility__show_dialog(result);
	if (window.parent && window.parent !== window) {
		window.parent.postMessage({
			type:'ficms-accessibility-result',
			result:result
		},'*');
	}
}

function accessibility__get_unique_name(el) {
	let tag = el.tagName.toLowerCase(), id = el.id ? '#' + el.id : '', className = '';
	if (el.className) {
		if (typeof el.className === 'string') className = el.className;
		else if (typeof el.className.baseVal === 'string') className = el.className.baseVal;
	}
	className = className ? '.' + className.split(' ').join('.') : '';
	return (tag == 'img' || tag == 'video') ? (el.outerHTML + ' ' + tag + id + className) : (tag + id + className);
}

function accessibility__get_unique_selector(el) {
	let better = ['mediaid','name','image'], parts = [], betterSel = '', idx = -1;
	while (el && el.nodeType === 1 && el.parentNode && el !== document.documentElement) {
		let tag = el.tagName.toLowerCase();
		if (el.id) { parts.push('#' + el.id); break; }
		if (tag == 'form' && el.getAttribute('name')) { parts.push('form[name=\"' + el.getAttribute('name') + '\"]'); break; }
		let siblings = [...el.parentNode.children].filter(sibling => sibling === el || !sibling.hasAttribute('data-ficms-accessibility-stage')),
			i = siblings.indexOf(el) + 1;
		tag += (i == 1) ? ':first-child' : (i == siblings.length ? ':last-child' : ':nth-child(' + i + ')');
		if (idx < 0) better.some(attr => {
			if (!el.hasAttribute('data-' + attr)) return false;
			betterSel = '[data-' + attr + '=\"' + el.getAttribute('data-' + attr) + '\"]';
			idx = parts.length;
			return true;
		});
		parts.push(tag);
		el = el.parentElement;
	}
	if (idx > -1) parts.splice(idx,(parts.length - idx),(parts.at(-1) + ' ' + betterSel));
	return parts.reverse().join(' > ');
}

function accessibility__clickarea(el, label = null, lookup = true) {
	// Inline prüfen
	let display = window.getComputedStyle(el).display;
	let parentTag = el.parentElement ? el.parentElement.tagName.toLowerCase() : "";
	let inlineContainers = ["p", "span", "li", "dd", "dt", "strong", "em"];
	let isInline = (display === "inline" || display === "inline-block") && inlineContainers.includes(parentTag);

	if (isInline) return true;

	// BoundingRect prüfen
	let rect = el.getBoundingClientRect();
	if (rect.width === 0 || rect.height === 0) return false;

	// Label prüfen
	if (!label && lookup) {
		label = document.querySelector(`label[for="${el.id}"]`) || el.closest("label");
	}

	if (label) {
		let labelRect = label.getBoundingClientRect();
		// Union-Rect berechnen
		let left = Math.min(rect.left, labelRect.left);
		let right = Math.max(rect.right, labelRect.right);
		let top = Math.min(rect.top, labelRect.top);
		let bottom = Math.max(rect.bottom, labelRect.bottom);
		let width = right - left;
		let height = bottom - top;

		// Fläche und Dimension prüfen
		if (width < 16 || height < 16) return false;
		if (width * height < 576) return false;
		return true;
	}

	// Ohne Label prüfen
	if (rect.width < 16 || rect.height < 16) return false;
	if (rect.width * rect.height < 576) return false;

	return true;
}

function accessibility__style_hash(el) {
	let s = window.getComputedStyle(el), combined = '';
	let keys = ['outline', 'boxShadow', 'border', 'borderColor', 'borderWidth', 'filter', 'transform', 'background', 'backgroundColor', 'color', 'scale', 'opacity'];
	for (let key of keys) combined += key + ':' + s[key] + ';';
	return combined.hashCode();
}

function accessibility__is_labeled(obj,recursion = true) {
	if (!obj) return '';
	let label = '';
	if (obj.hasAttribute('aria-label')) label = obj.getAttribute('aria-label');
	if ((!label || label == '') && obj.hasAttribute('aria-labelledby')) label = accessibility__is_labeled(helper__get_by_attribute(obj,'aria-labelledby'),false);
	if ((!label || label == '') && obj.hasAttribute('alt')) label = obj.getAttribute('alt');
	if ((!label || label == '') && obj.hasAttribute('title')) label = obj.getAttribute('title');
	if ((!label || label == '') && obj.hasAttribute('placeholder')) label = obj.getAttribute('placeholder');
	if (!label || label == '') label = obj.textContent.trim();
	if ((!label || label == '') && recursion) {
		Array.from(obj.querySelectorAll('img[alt],[aria-label],[aria-labelledby]')).some(elem => {
			let innerlabel = accessibility__is_labeled(elem,false);
			if (!innerlabel || innerlabel == '') return false;
			label = innerlabel;
			return true;
		});
	} return label;
}

function accessibility__find_last_parent(hierarchy, level) {
    let last = hierarchy[hierarchy.length - 1];
    if (!last || last.level >= level) return null;
    while (last.children.length > 0 && last.children[last.children.length - 1].level < level) {
        last = last.children[last.children.length - 1];
    } return last;
}

function accessibility__init_media_alt(el) {
	if (el.getAttribute("aria-hidden") === "true") return { status: "ignored" };

	if (el.tagName.toLowerCase() === "img") {
		let alt = el.getAttribute("alt") || "", wordCount = alt.trim().split(/\s+/).length, role = el.getAttribute("role");

		if (role === "presentation" || role === "decorative") return { status: "ignored" };
		if (!el.hasAttribute("alt")) return { status: "error", reason: "_accessibility_alt_missing", image:el.outerHTML };
		if (alt === "") return { status: "ignored" };
		if (wordCount < 2) return { status: "warning", reason: "_accessibility_alt_non_descriptive", image:el.outerHTML, value:alt };
	} else if (el.tagName.toLowerCase() === "video") {
		let hasCaptions = el.querySelector("track[kind='captions']"),
			hasSubtitles = el.querySelector("track[kind='subtitles']"),
			hasDescriptions = el.querySelector("track[kind='descriptions']");
		if (!hasCaptions && !hasSubtitles && !hasDescriptions) return { status: "error", image:el.outerHTML, reason: "_accessibility_video_missing_captions" };
    } return { status: "success" };
}

function accessibility__init_navigatability(el,source = el,stage = false) {
	let focusable = source.matches('a, button, input, select, textarea, summary, [tabindex]');
	if (focusable || typeof source.onclick === "function" || typeof source.onmousedown === "function" || typeof source.onmouseup === "function") {
		// tabindex=-1 nimmt Elemente aus der Tastatur-Navigation (z.B. Honeypots) - keine Fokus-Ziele
		if (source.hasAttribute("tabindex") && parseInt(source.getAttribute("tabindex")) < 0) return { status: "ignored" };
		let style = window.getComputedStyle(el), tag = source.tagName.toLowerCase(), sourceLabel = document.querySelector(`label[for="${source.id}"]`) || source.closest("label"),
			labelEl = stage ? stage.target(sourceLabel) : sourceLabel;
		if (stage && stage.root && labelEl === sourceLabel) labelEl = null;

		// Input hidden mit Label
		if (tag === "input" && source.type !== 'hidden' && style.display === "none" && sourceLabel) {
			return { status: "error", reason: "_accessibility_input_hidden_with_label" };
		}
		if ((tag === "button" || tag === "a") && style.display === "none") return { status: "ignored" };

		// Leerer Link
		if (tag === "a" && source.textContent.trim() === "") {
			let labelText = accessibility__is_labeled(source);
			if (labelText && labelText.trim().length > 5) return { status: "success" };
			return { status: "error", reason: "_accessibility_link_no_label" };
		}

		// Interaktive, aber ohne Tastatur-Support
		if (tag !== "button" && tag !== "a" && (source.hasAttribute("onclick") || source.hasAttribute("onmousedown") || source.hasAttribute("onmouseup"))) {
			let hasTabindex = source.hasAttribute("tabindex"),
				hasRole = source.hasAttribute("role"),
				hasKeyboardEvents = source.hasAttribute("onkeydown") || source.hasAttribute("onkeypress");
			if (!hasTabindex && !hasKeyboardEvents) return { status: "error", reason: "_accessibility_interactive_no_keyboard_support" };
			if (!hasRole) return { status: "warning", reason: "_accessibility_interactive_no_role" };
		}

		// Klickfläche prüfen
		if (!accessibility__clickarea(el,labelEl,!stage || !stage.root)) return { status: "warning", reason: "_accessibility_focus_target_too_small" };

		// Prüfen, ob das Element durch ein anderes fixed-Element überlappt wird
		let position = stage && stage.root ? stage.inView(() => getComputedStyle(el).position) : style.position;
		if (position === "fixed") {
			let overlapped = (stage || {inView:callback => callback()}).inView(() => {
				let rect = el.getBoundingClientRect(), points = [
					[rect.left + 1, rect.top + 1],
					[rect.right - 1, rect.top + 1],
					[rect.left + 1, rect.bottom - 1],
					[rect.right - 1, rect.bottom - 1]
				];
				for (let [x, y] of points) {
					let topEl = document.elementFromPoint(x, y);
					if (topEl && topEl !== el && !el.contains(topEl)) {
						let topStyle = getComputedStyle(topEl);
						if (topStyle.position === "fixed" && topStyle.pointerEvents !== "none") return true;
					}
				} return false;
			});
			if (overlapped) return { status: "error", reason: "_accessibility_focus_target_overlapped" };
		}

		// Fokus sichtbar-Prüfung
		let targets = [], checkElements = [
			el,labelEl,el.parentElement,
			(el.parentElement ? el.parentElement.parentElement : null)
		]; checkElements.forEach(elem => {
			if (elem) targets.push({ref:elem,pre:accessibility__style_hash(elem)});
		});

			// Fokus setzen
			let visible = typeof el.checkVisibility !== 'function' || el.checkVisibility();
			if (visible) try { el.focus({ preventScroll: true, focusVisible:true }); } catch(e) {}
			if (!visible || document.activeElement !== el) return visible ? { status: "warning", reason: "_accessibility_focus_not_focusable" } : { status: "ignored" };

		// Prüfen, ob sich die Styles geändert haben (Fokus sichtbar)
		let changed = targets.some(t => t.pre !== accessibility__style_hash(t.ref));
		return changed ? { status: "success" } : { status: "warning", reason: "_accessibility_focus_no_outline" };
	} return { status: "ignored" };
}

function accessibility__init_readability(el,source = el) {
	let cs = window.getComputedStyle(el), fs = parseFloat(cs.fontSize),
		weight = parseInt(cs.fontWeight), isLargeText = (fs >= 24) || (fs >= 18.66 && weight >= 700);

	let textNodes = [...el.childNodes].filter(n => n.nodeType === Node.TEXT_NODE && n.nodeValue.trim().length);
	if (!textNodes.length) return {status: "ignored"};
	if (!accessibility__text_rects(el).length || (typeof el.checkVisibility === 'function' && !el.checkVisibility({checkOpacity:true,checkVisibilityCSS:true}))) return {status: "ignored"};

	// br- und Block-Grenzen zwischen den Textknoten sind Satzgrenzen — sonst verschmelzen
	// Überschriften- und Adresszeilen ohne Satzzeichen zu Schein-Langsätzen
	let segments = [], buffer = [];
	[...el.childNodes].forEach(n => {
		if (n.nodeType === Node.TEXT_NODE) {
			if (n.nodeValue.trim().length) buffer.push(n.nodeValue.trim());
			return;
		}
		if (n.nodeType === Node.ELEMENT_NODE && (n.tagName === 'BR' || window.getComputedStyle(n).display !== 'inline') && buffer.length) {
			segments.push(buffer.join(" "));
			buffer = [];
		}
	});
	if (buffer.length) segments.push(buffer.join(" "));
	let text = segments.join(". ");
	if (!/[\p{L}\p{N}]/u.test(text)) return {status: "ignored"};
	let sents = text.replace(/[.!?]+/g, '.').split('.').map(s => s.trim()).filter(Boolean),
		longSents = sents.filter(s => s.split(/\s+/).length > 40).length;

	if (fs < 16) return {status: "warning", reason: "_accessibility_text_too_small"};
	if (longSents > 0) return {status: "warning", reason: "_accessibility_text_too_long"};

	let colors = fiCMS.accessibility.contrastColors.get(source) || helper__get_contrast_colors(el), captured = fiCMS.accessibility.contrastPixels.get(source),
		contrast = captured ? captured.contrast : helper__get_contrast(colors.color,colors.background);
	if (colors.complex.length && !captured) throw new Error('Contrast pixels fehlen fuer ' + accessibility__get_unique_selector(source));
	let passed = helper__get_contrast_sufficient(contrast, el);
	return (passed) ? {status: "success"} : {status: "error", value: contrast, reason: "_accessibility_text_low_contrast"};
}

function accessibility__init_form_labels(el) {
	if (el.type === "hidden") return { status: "ignored" };

	let id = el.id,
		label = document.querySelector(`label[for="${id}"]`),
		parentLabel = el.closest("label"),
		ariaLabel = el.getAttribute("aria-label"),
		ariaLabelledby = el.getAttribute("aria-labelledby"),
		placeholder = el.getAttribute("placeholder");

	let isValid = val => val && val.trim().length > 0;

	// Prüfen, ob aria-labelledby existiert und Text hat
	let ariaLabelledbyValid = false;
	if (isValid(ariaLabelledby)) {
		let ref = document.getElementById(ariaLabelledby);
		if (ref && isValid(ref.textContent)) ariaLabelledbyValid = true;
	}

	// Native sichtbare Labels oder aria-label oder valides aria-labelledby => success
	if (label || parentLabel || isValid(ariaLabel) || ariaLabelledbyValid) return { status: "success" };

	// Nur placeholder vorhanden
	if (isValid(placeholder)) return { status: "warning", reason: "_accessibility_form_placeholder_only" };

	// Nichts gefunden
	return { status: "error", reason: "_accessibility_form_missing_label" };
}

function accessibility__init_aria(el) {
	let ariaLabelledby = el.getAttribute("aria-labelledby"),
		ariaDescribedby = el.getAttribute("aria-describedby"),
		ariaControls = el.getAttribute("aria-controls"),
		role = el.getAttribute("role"),
		tag = el.tagName.toLowerCase();

	let redundantRoles = {
		nav: ['navigation'],
		footer: ['contentinfo'],
		header: ['banner'],
		main: ['main'],
		aside: ['complementary']
	};

	let forbiddenRoles = {
		form: ['group', 'region'],
		ul: ['list'],
		ol: ['list'],
		dl: ['list'],
		table: ['grid', 'treegrid', 'list'],
		section: ['region']
	};

	if (ariaLabelledby && !document.getElementById(ariaLabelledby))
		return { status: "error", reason: "_accessibility_aria_invalid_labelledby" };

	if (ariaDescribedby && !document.getElementById(ariaDescribedby))
		return { status: "error", reason: "_accessibility_aria_invalid_describedby" };

	if (ariaControls && !document.getElementById(ariaControls))
		return { status: "error", reason: "_accessibility_aria_invalid_controls" };

	// Verbotene Rollen prüfen
	if (role && forbiddenRoles[tag]?.includes(role)) {
		if (tag === "section" && role === "region") {
			if (!el.hasAttribute("aria-label") && !el.hasAttribute("aria-labelledby")) {
				return { status: "error", reason: "_accessibility_region_no_label" };
			} else {
				return { status: "warning", reason: "_accessibility_aria_role_redundant" };
			}
		}

		// Alle anderen forbiddenRoles bleiben Fehler
		return { status: "error", reason: "_accessibility_aria_role_forbidden" };
	}

	// Redundante Rollen prüfen
	if (role && redundantRoles[tag]?.includes(role))
		return { status: "warning", reason: "_accessibility_aria_role_redundant" };

	if (["section", "aside"].includes(tag) && !role) {
		let hasHeading = el.querySelector("h1,h2,h3,h4,h5,h6");
		if (!hasHeading && !el.hasAttribute("aria-label") && !el.hasAttribute("aria-labelledby")) {
			return { status: "warning", reason: "_accessibility_region_no_label" };
		}
	}

	return { status: "success" };
}

function accessibility__init_user_preferences(el) {
	let cs = window.getComputedStyle(el);

	let hasAnimation = accessibility__has_motion_animation(cs);
	let hasTransition = accessibility__has_motion_transition(cs);

	if (!hasAnimation && !hasTransition) return {status:"ignored"};
	let optInAnimation = hasAnimation && accessibility__motion_preference_opt_in(el,'animation',cs),
		optInTransition = hasTransition && accessibility__motion_preference_opt_in(el,'transition',cs),
		value = accessibility__motion_description(cs,hasAnimation,hasTransition);
	if ((!hasAnimation || optInAnimation) && (!hasTransition || optInTransition)) return {status:"success"};

	let rules = fiCMS.accessibility.reducedMotion || [];
	let matches = rules.filter(r => {
		return accessibility__selector_matches(el,r.selector);
	});

	if (!matches.length) return {status:"warning",reason:"_accessibility_prefers_reduced_motion_missing",value:value};

	let reducesAnim = optInAnimation, reducesTrans = optInTransition, reducesScroll = false;

	matches.some(r => {
		let css = r.css;

		// animation reduziert?
		if (hasAnimation && !reducesAnim) {
			if (/(^|;)\s*animation\s*:\s*none\b/.test(css)) reducesAnim = true;
			else if (/(^|;)\s*animation-name\s*:\s*none\b/.test(css)) reducesAnim = true;
			else {
				// animation-duration oder animation shorthand
				let m1 = css.match(/(^|;)\s*animation-duration\s*:\s*([^;]+)/);
				let m2 = css.match(/(^|;)\s*animation\s*:\s*([^;]+)/);
				let ms1 = m1 ? accessibility__css_time_max(m1[2]) : null;
				let ms2 = m2 ? accessibility__css_time_max(m2[2]) : null;

				// wenn irgendein Wert existiert und <=150ms ist -> reduziert
				if (ms1 !== null && ms1 <= 150) reducesAnim = true;
				else if (ms2 !== null && ms2 <= 150) reducesAnim = true;

				// typische pattern: 0.01ms + iteration-count:1
				if (!reducesAnim && /(animation-duration\s*:\s*0(\.0+)?(ms|s)|animation\s*:\s*[^;]*0(\.0+)?(ms|s))/.test(css)) reducesAnim = true;
			}
		}

		// transition reduziert?
		if (hasTransition && !reducesTrans) {
			if (/(^|;)\s*transition\s*:\s*none\b/.test(css)) reducesTrans = true;
			else if (/(^|;)\s*transition-property\s*:\s*none\b/.test(css)) reducesTrans = true;
			else {
				let m1 = css.match(/(^|;)\s*transition-duration\s*:\s*([^;]+)/);
				let m2 = css.match(/(^|;)\s*transition\s*:\s*([^;]+)/);
				let ms1 = m1 ? accessibility__css_time_max(m1[2]) : null;
				let ms2 = m2 ? accessibility__css_time_max(m2[2]) : null;

				if (ms1 !== null && ms1 <= 150) reducesTrans = true;
				else if (ms2 !== null && ms2 <= 150) reducesTrans = true;

				if (!reducesTrans && /(transition-duration\s*:\s*0(\.0+)?(ms|s)|transition\s*:\s*[^;]*0(\.0+)?(ms|s))/.test(css)) reducesTrans = true;
			}
		}

		// scroll-behavior auto ist oft Teil von reduced-motion
		if (!reducesScroll && /(^|;)\s*scroll-behavior\s*:\s*(auto|initial|unset)\b/.test(css)) reducesScroll = true;

		return (reducesAnim || !hasAnimation) && (reducesTrans || !hasTransition);
	});

	if ((hasAnimation && !reducesAnim) || (hasTransition && !reducesTrans)) {
		return {status:"warning",reason:"_accessibility_prefers_reduced_motion_too_long",value:value};
	}

	return {status:"success"};
}

function accessibility__check_user_preferences(elements, scores, accessibility) {
	scores.user_preferences = {total:0,success:0,warning:0,error:0};

	elements.forEach(({obj}) => {
		if (typeof obj.checkVisibility === 'function' && !obj.checkVisibility()) return;
		let result = accessibility__init_user_preferences(obj);
		if (result.status === 'ignored') return;
		scores.user_preferences.total++;
		scores.user_preferences[result.status]++;
		if (result.status !== 'warning') return;

		if (!accessibility.warning[result.reason]) accessibility.warning[result.reason] = [];
		accessibility.warning[result.reason].push({
			id:uniqueId(),
			name:accessibility__get_unique_name(obj),
			value:result.value ?? false,
			image:false,
			unique:accessibility__get_unique_selector(obj)
		});
	});
}

function accessibility__init_headlines(el) {
	if (el.closest("dialog")) return { status: "ignored" };
    let level = parseInt(el.tagName.charAt(1)), style = window.getComputedStyle(el), text = el.innerText.trim();
    if (style.visibility === "hidden") return { status: "ignored" }; // Unsichtbare Überschriften ignorieren

    // **Level speichern (wichtig für die spätere Prüfung)**
    fiCMS.accessibility.headline.level.push(level);
	let node = { level, element: el, text, children: [] };

    if (fiCMS.accessibility.headline.hierarchie.length === 0) fiCMS.accessibility.headline.hierarchie.push(node);
    else {
        let parent = accessibility__find_last_parent(fiCMS.accessibility.headline.hierarchie, level);
        if (parent) parent.children.push(node);
        else fiCMS.accessibility.headline.hierarchie.push(node);
    }

	// Kurz?
	return (text.length < 3) ? { status: "warning", reason: "_accessibility_headline_too_short" } : { status: "success" };
}

function accessibility__check_headline_structure(scores, accessibility) {
	let { hierarchie, level } = fiCMS.accessibility.headline;

	function findElementByLevel(nodes, targetLevel) {
		for (let node of nodes) {
			if (node.level === targetLevel) return node.element;
			let found = findElementByLevel(node.children, targetLevel);
			if (found) return found;
		} return null;
	}

	if (typeof scores.semantic === "undefined") scores.semantic = { total:0, success:0, warning:0, error:0 };

	// Kein h1
	scores.semantic.total++;
	if (!level.includes(1)) {
		accessibility.error["_accessibility_headline_missing_h1"] = [];
		scores.semantic.error++;
	} else scores.semantic.success++;

	// Mehrere h1
	scores.semantic.total++;
	if (level.filter(l => l === 1).length > 1) {
		accessibility.error["_accessibility_headline_multiple_h1"] = [];
		scores.semantic.error++;
	} else scores.semantic.success++;

	// Hierarchie
	scores.semantic.total++;
	let lastLevel = 0, firstInvalid = null;
	for (let l of level) {
		if (l > lastLevel + 1) {
			firstInvalid = l;
			break;
		} lastLevel = l;
	} if (firstInvalid) {
		let firstInvalidElement = findElementByLevel(hierarchie, firstInvalid);
		if (firstInvalidElement) {
			accessibility.error["_accessibility_headline_hierarchy"] = [{
				id: uniqueId(),
				name: accessibility__get_unique_name(firstInvalidElement),
				unique: accessibility__get_unique_selector(firstInvalidElement)
			}];
			scores.semantic.error++;
		}
	} else scores.semantic.success++;

	return { scores, accessibility };
}

function accessibility__elements(parent = document.body, elements = [], stateMap = new Map(), parents = []) {
	let ignoredTags = [
		"br","hr","wbr","template","slot","track","source","meta","link","script","style","noscript",
		"col","colgroup","caption","thead","tbody","tfoot","frame","frameset","noframes"
	];
	let adminSelectors = ".picker, .pagesPicker, .clearLogout, .pages__admin, .pages_hidden, .pagesFile, .pagesTarget, .loading, .settings__datalists";
	let roleMap = { "main":"main", "banner":"header", "navigation":"nav", "contentinfo":"footer" };
	let landmarkTags = ["main","header","nav","footer"];

	for (let el of parent.children) {
		let tag = el.tagName.toLowerCase();

		if (
			ignoredTags.includes(tag) ||
			el.closest("[aria-hidden='true']") ||
			el.closest(adminSelectors) ||
			el.hasAttribute("hidden") ||
			el.getAttribute("aria-disabled") === "true" ||
			el.disabled
		) continue;

		let style = window.getComputedStyle(el);
		if (style.display === "none" || style.visibility === "hidden") continue;

		// Landmark zählen
		let landmark = landmarkTags.includes(tag) ? tag : false;
		if (!landmark && el.hasAttribute("role")) landmark = roleMap[el.getAttribute("role")] || false;
		if (landmark) fiCMS.accessibility.landmarks[landmark]++;

		// Skiplink prüfen
		if (tag === "a" && !fiCMS.accessibility.skip && el.hash) {
			let targetId = el.hash.substring(1), target = document.getElementById(targetId);
			if (target && (target.tagName === "MAIN" || target.getAttribute("role") === "main" || target.id === "pagescontent")) {
				let landmarks = fiCMS.accessibility.landmarks;
				fiCMS.accessibility.skip = landmarks.main === 0 && landmarks.nav === 0 ? true : (landmarks.nav > 0 ? "nav" : "main");
			}
		}

		// Interaktiv, aber disabled oder hidden
		if (
			el.matches("a, button, input, select, textarea, [tabindex]") &&
			(
				el.disabled ||
				el.getAttribute("aria-disabled") === "true" ||
				el.hasAttribute("hidden") ||
				el.closest("fieldset[disabled]")
			)
		) continue;

		// Container-Handling
		let newParents = [...parents];
		if (tag === "dialog" || tag === "details") {
			newParents.push(el);
			if (!stateMap.has(el)) stateMap.set(el, el.open);
		}

		// Element speichern
		elements.push({ obj: el, parents: newParents });

		// Rekursiv Kinder prüfen
		if (el.children.length) accessibility__elements(el, elements, stateMap, newParents);
	}

	return {
		elements,
		states: Array.from(stateMap.entries()).map(([el, wasOpen]) => ({ el, wasOpen }))
	};
}

async function accessibility__init() {
	if (!accessibility__test_focus()) throw new Error('Accessibility focus self-test failed');

	fiCMS.accessibility = {
		skip: false,
		landmarks: { main:0, header:0, footer:0, nav:0 },
		headline: { level:[], hierarchie:[] },
		reducedMotion: accessibility__collect_motion_preference_rules('reduce'),
		motionOptIns: accessibility__collect_motion_preference_rules('no-preference'),
		motionAnimations: accessibility__collect_motion_animation_names()
	};

	let checks = [
		{ function: accessibility__init_media_alt, selector: "img, video", name: "media_alt" },
		{ function: accessibility__init_navigatability, selector: "*", name: "navigatability", rendered:true },
		{ function: accessibility__init_form_labels, selector: "input, textarea, select", name: "form_labels" },
		{ function: accessibility__init_headlines, selector: "h1, h2, h3, h4, h5, h6", name: "semantic" },
		{ function: accessibility__init_aria, selector: "section, aside, [aria-labelledby], [aria-describedby], [aria-controls], [role]", name: "semantic" },
		{ function: accessibility__init_readability, selector: "*", name: "readability", rendered:true }
	];

	let accessibility = { error:{}, warning:{} }, scores = Object.fromEntries(
		['media_alt','navigatability','form_labels','semantic','readability','user_preferences'].map(category => [category,{total:0,success:0,warning:0,error:0}])
	);
	let { elements, states } = accessibility__elements();
	await accessibility__contrast_capture(elements,states);
	let stateMap = new WeakMap(states.map(({el,wasOpen}) => [el,wasOpen]));
	let stats = {
		totalElements: document.body ? document.body.querySelectorAll('*').length : 0,
		collectedElements: elements.length,
		checkRuns: 0
	};

	for (let group of accessibility__state_groups(elements)) {
		let stage = accessibility__stage_create(group,stateMap);
		try {
			group.elements.forEach(obj => checks.forEach(({ function: checkFunction, selector, name, rendered }) => {
				if (typeof scores[name] === "undefined") scores[name] = { total:0, success:0, warning:0, error:0 };

				if (selector === "*" || obj.matches(selector)) {
					stats.checkRuns++;
					try {
						let result = checkFunction(rendered ? stage.target(obj) : obj,obj,stage);
						if (result.status !== "ignored") {
							scores[name].total++;
							scores[name][result.status]++;
							if (result.status !== "success") {
								if (!accessibility[result.status][result.reason]) accessibility[result.status][result.reason] = [];
								accessibility[result.status][result.reason].push({
									id: uniqueId(),
									name: accessibility__get_unique_name(obj),
									value: result.value ?? false,
									image: result.image ?? false,
									unique: accessibility__get_unique_selector(obj)
								});
							}
						}
					} catch (error) {
						// Elemente, die während des Audits aus dem DOM entfernt wurden, dürfen das Gesamt-Audit nicht abbrechen
					}
				}
			}));
		} finally {
			stage.remove();
		}
	}

	// Bewegungspräferenzen nach dem zustandsabhängigen DOM-Traversal prüfen.
	accessibility__check_user_preferences(elements,scores,accessibility);

	// Landmark-Regeln prüfen
	let landmarkRules = {
		main: { min:1, max:1 },
		nav: { min:1, max:0 },
		header: { min:0, max:1 },
		footer: { min:0, max:1 }
	};
	Object.entries(landmarkRules).forEach(([landmark, rules]) => {
		let count = fiCMS.accessibility.landmarks[landmark] || 0;
		let type = (count < rules.min) ? "missing" : "multiple";
		if (typeof scores["semantic"] === "undefined") scores["semantic"] = { total:0, success:0, warning:0, error:0 };
		scores["semantic"].total++;
		if (count < rules.min || (rules.max > 0 && count > rules.max)) {
			accessibility.error[`_accessibility_aria_${type}_${landmark}`] = [];
			scores["semantic"].error++;
		} else {
			scores["semantic"].success++;
		}
	});

	// Skiplink prüfen
	if (typeof scores["navigatability"] === "undefined") {
		scores["navigatability"] = { total:0, success:0, warning:0, error:0 };
	}
	scores["navigatability"].total++;
	let errortype = "success", skipReason = null;
	if (fiCMS.accessibility.skip === false) {
		errortype = "warning";
		skipReason = "none";
	} else if (["nav", "main"].includes(fiCMS.accessibility.skip)) {
		errortype = "error";
		skipReason = "invalid";
	}

	scores["navigatability"][errortype]++;
	if (errortype !== "success") accessibility[errortype][`_accessibility_navigatability_skip_${skipReason}`] = [];

	// Überschriften-Hierarchie prüfen
	let result = accessibility__check_headline_structure(scores, accessibility);
	result.stats = stats;
	return result;
}

async function accessibility__start() {
	if (!document.body || !document.body.hasAttribute('data-loaded')) await new Promise(resolve => document.addEventListener('ficms:loaded',resolve,{once:true}));
	// scroll zählt als Interaktion: Browser-Scroll-Restore, Anker-Sprünge und Scrollbar-Drag feuern keine wheel/pointer-Events;
	// das Audit selbst scrollt nie (alle focus-Aufrufe mit preventScroll) — jede Positionsänderung ist extern und bleibt unangetastet
	let active = document.activeElement, interacted = false,
		interaction = () => interacted = true, events = ['wheel','touchstart','pointerdown','keydown','scroll'];
	events.forEach(event => window.addEventListener(event,interaction,{capture:true,passive:true}));
	try {
		return await accessibility__init();
	} finally {
		events.forEach(event => window.removeEventListener(event,interaction,{capture:true}));
		if (!interacted) {
			if (active && active !== document.body && active.isConnected && typeof active.focus === 'function') active.focus({preventScroll:true});
			else if (document.activeElement && document.activeElement !== document.body && typeof document.activeElement.blur === 'function') document.activeElement.blur();
		}
	}
}

export const AccessibilityAudit = {
	version:'0.1.2',
	run(options = {}) {
		if (options.document && options.document !== document) throw new Error('Accessibility audit document mismatch');
		return accessibility__start();
	}
};
