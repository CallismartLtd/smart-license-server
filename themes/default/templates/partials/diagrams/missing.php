<?php
/**
 * Diagram: the signal travels, but nothing answers at the other end (4xx).
 *
 * Same drawing as the built-in error document's 4xx diagram.
 *
 * @author Callistus Nwachukwu
 */

?>
<svg viewBox="0 0 260 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<rect x="20" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" />
	<circle cx="34" cy="70" r="3" fill="var(--ink)" />
	<line x1="46" y1="63" x2="66" y2="63" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
	<line x1="46" y1="77" x2="66" y2="77" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />

	<path class="signal-line" d="M76 70 H166" stroke="var(--accent)" stroke-width="2" stroke-dasharray="4 5" />
	<circle cx="178" cy="70" r="11" stroke="var(--accent)" stroke-width="1.5" stroke-dasharray="3 4" />

	<rect x="204" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" stroke-dasharray="3 4" opacity="0.35" />
</svg>