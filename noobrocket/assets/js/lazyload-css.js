function noobrocket_css_lazyload_launch() {
	const pairs = typeof noobrocket_pairs === 'undefined' ? [] : noobrocket_pairs;
	const container = document.querySelector('#noobrocket-lazyload-bg-container');
	if (!container) return;

	const excluded = typeof noobrocket_excluded_pairs === 'undefined' ? [] : noobrocket_excluded_pairs;
	excluded.forEach(pair => {
		try {
			document.querySelectorAll(pair.selector).forEach(element => {
				element.setAttribute(`data-noobrocket-lazy-bg-${pair.hash}`, 'excluded');
			});
		} catch (error) {
			console.error(error);
		}
	});
	if (!pairs.length) return;

	// Published rules also apply to future targets. Only unpublished mappings
	// need further discovery and intersection observation.
	const pending = new Set(pairs.map(pair => ({ ...pair, elements: new Set() })));
	const observed = new Set();
	let observer;
	let mutations;
	let frame = 0;

	function publish(pair) {
		const style = document.createElement('style');
		style.textContent = pair.style;
		container.insertAdjacentElement('afterend', style);
		pending.delete(pair);
		pair.elements.forEach(element => {
			element.setAttribute(`data-noobrocket-lazy-bg-${pair.hash}`, 'loaded');
		});
		if (!pending.size) {
			if (mutations) mutations.disconnect();
			if (observer) observer.disconnect();
			if (frame) cancelAnimationFrame(frame);
		}
	}

	if (!window.IntersectionObserver || !window.MutationObserver) {
		pending.forEach(publish);
		return;
	}

	const threshold = noobrocket_lazyload_css_data.threshold || 300;
	observer = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting) return;
			pending.forEach(pair => {
				if (pair.elements.has(entry.target)) publish(pair);
			});
		});
		unobserveUnused();
	}, { rootMargin: threshold + 'px' });

	function unobserveUnused() {
		observed.forEach(element => {
			if ([...pending].some(pair => pair.elements.has(element))) return;
			observer.unobserve(element);
			observed.delete(element);
		});
	}

	function discover() {
		frame = 0;
		pending.forEach(pair => {
			const elements = new Set();
			try {
				document.querySelectorAll(pair.selector).forEach(element => {
					const state = element.getAttribute(`data-noobrocket-lazy-bg-${pair.hash}`);
					if (state === 'loaded' || state === 'excluded') return;
					elements.add(element);
					if (observed.has(element)) return;
					observer.observe(element);
					observed.add(element);
				});
			} catch (error) {
				console.error(error);
			}
			pair.elements = elements;
		});
		unobserveUnused();
	}

	mutations = new MutationObserver(records => {
		if (frame || !pending.size) return;
		const relevant = records.some(record => record.type !== 'attributes' || !record.attributeName.startsWith('data-noobrocket-lazy-bg-'));
		if (relevant) frame = requestAnimationFrame(discover);
	});
	mutations.observe(document.body, { attributes: true, childList: true, subtree: true });
	discover();
}

if (document.body) {
	noobrocket_css_lazyload_launch();
} else {
	document.addEventListener('DOMContentLoaded', noobrocket_css_lazyload_launch, { once: true });
}
