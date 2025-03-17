import Cookies from 'js-cookie';
import { compare } from 'compare-versions'

import 'animate.css';

const versionEl = document.getElementById('coopcycle-version');
const version = versionEl.innerHTML;

const lastViewedVersion = Cookies.get('__changelog_latest')

if (version !== 'dev-master') {

    const shouldHighlight = lastViewedVersion ? compare(version, lastViewedVersion, '>') : true;

    versionEl.addEventListener('click', () => Cookies.set('__changelog_latest', version));

    if (shouldHighlight) {
        versionEl.classList.add('font-weight-bold');
        versionEl.classList.add('text-warning');
        versionEl.classList.add('animate__animated', 'animate__delay-2s', 'animate__repeat-3', 'animate__headShake');
    }
}
