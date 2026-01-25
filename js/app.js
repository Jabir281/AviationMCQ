/**
 * Aviation MCQ Test Application
 * Main JavaScript file
 */

// ============================================
// App State
// ============================================
const state = {
    subjects: {},
    subjectOrder: [],
    pendingSubject: null,
    currentSubject: null,
    subjectSection: null, // 'seen' | 'unseen'
    mode: null, // 'mock' | 'practice'
    examTotalTimeSec: 0,
    questionLimit: null,
    timerId: null,
    timeRemainingSec: 0,
    questions: [],
    currentQuestionIndex: 0,
    userAnswers: [], // -1 = not answered, 0-3 = selected option
    examFinished: false
};

const LAST_MOCK_CONFIG_KEY = 'lastMockExamConfig';
const LAST_MOCK_WRONG_CONFIG_KEY = 'lastMockWrongExamConfig';
const LAST_PRACTICE_WRONG_CONFIG_KEY = 'lastPracticeWrongExamConfig';

let postUserLoginAction = null;
let cachedUserLoggedIn = null;
let postSubjectSectionAction = null;

// Subject icons mapping
const subjectIcons = {
    'COMS': '📡',
    'HPL': '🧠',
    'OPS': '📋',
    'RNAV': '🛰️',
    'FPL': '✈️',
    'default': '📚'
};

// ============================================
// Navigation Bar
// ============================================
function toggleNav() {
    const navMenu = document.getElementById('nav-menu');
    const navToggle = document.querySelector('.nav-toggle');
    navMenu.classList.toggle('open');
    navToggle.classList.toggle('active');
}

function closeNav() {
    const navMenu = document.getElementById('nav-menu');
    const navToggle = document.querySelector('.nav-toggle');
    navMenu.classList.remove('open');
    navToggle.classList.remove('active');
}

// Close nav when clicking outside
document.addEventListener('click', (e) => {
    const navbar = document.querySelector('.navbar');
    if (navbar && !navbar.contains(e.target)) {
        closeNav();
    }
});

// ============================================
// Page Navigation
// ============================================
function showPage(pageId) {
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    document.getElementById(pageId).classList.add('active');
}

function goHome() {
    showPage('home-page');
}

function showSubjects() {
    requireUserLogin(() => {
        showPage('subject-page');
        openSubjectSectionPicker(() => loadSubjects());
    });
}

function openSubjectSectionPicker(actionAfterPick) {
    postSubjectSectionAction = typeof actionAfterPick === 'function' ? actionAfterPick : null;
    const overlay = document.getElementById('subject-section-overlay');
    if (overlay) overlay.classList.add('active');
}

function closeSubjectSectionPicker() {
    const overlay = document.getElementById('subject-section-overlay');
    if (overlay) overlay.classList.remove('active');
}

function cancelSubjectSectionPicker() {
    closeSubjectSectionPicker();
    postSubjectSectionAction = null;

    // Give a clear prompt so users don't think the app is stuck.
    const grid = document.getElementById('subject-grid');
    if (grid) {
        grid.innerHTML = `
            <div class="history-empty" style="max-width: 520px; margin: 0 auto;">
                <div class="empty-icon">📚</div>
                <p>Please choose <strong>Seen</strong> or <strong>Unseen</strong> to view subjects.</p>
                <button class="btn btn-primary" onclick="openSubjectSectionPicker(() => loadSubjects())">Choose Now</button>
            </div>
        `;
    }
}

function chooseSubjectSection(section) {
    const s = String(section || '').toLowerCase();
    if (s !== 'seen' && s !== 'unseen') return;
    state.subjectSection = s;

    const next = postSubjectSectionAction;
    postSubjectSectionAction = null;
    closeSubjectSectionPicker();
    if (typeof next === 'function') next();
}

function showQuiz() {
    showPage('quiz-page');
}

function showResults() {
    showPage('results-page');
    displayResults();
}

function refreshRetryWrongNav() {
    // Handle retry wrong for mock tests
    const linkMock = document.getElementById('nav-retry-wrong');
    if (linkMock) {
        let hasWrongMock = false;
        try {
            const raw = localStorage.getItem(LAST_MOCK_WRONG_CONFIG_KEY);
            const parsed = raw ? JSON.parse(raw) : null;
            hasWrongMock = !!(parsed && parsed.subjectCode && Array.isArray(parsed.questionIds) && parsed.questionIds.length > 0);
        } catch (_) {
            hasWrongMock = false;
        }
        linkMock.style.display = hasWrongMock ? 'flex' : 'none';
    }

    // Handle retry wrong for practice
    const linkPractice = document.getElementById('nav-retry-practice');
    if (linkPractice) {
        let hasWrongPractice = false;
        try {
            const raw = localStorage.getItem(LAST_PRACTICE_WRONG_CONFIG_KEY);
            const parsed = raw ? JSON.parse(raw) : null;
            hasWrongPractice = !!(parsed && parsed.subjectCode && Array.isArray(parsed.questionIds) && parsed.questionIds.length > 0);
        } catch (_) {
            hasWrongPractice = false;
        }
        linkPractice.style.display = hasWrongPractice ? 'flex' : 'none';
    }
}

// ============================================
// Data Loading
// ============================================
async function fetchJsonApiFirst(apiUrl, fallbackUrl) {
    // Try API first (Hostinger). If it fails (e.g. local static server), use fallback.
    try {
        const apiRes = await fetch(apiUrl, { cache: 'no-store' });
        const apiData = await apiRes.json().catch(() => null);

        // If API exists but requires login, do NOT fall back to local JSON.
        if (apiRes.status === 401 || apiRes.status === 403) {
            cachedUserLoggedIn = false;
            throw Object.assign(new Error('AUTH_REQUIRED'), { authRequired: true, apiData });
        }

        if (apiRes.ok) {
            // Our API wraps responses as { ok: true, subjects/questions: [...] }
            if (apiData && apiData.ok === true) return apiData;
        }
    } catch (err) {
        // If auth is required, do NOT fall back.
        if (err && err.authRequired === true) throw err;
        // Otherwise ignore and try fallback.
    }

    const res = await fetch(fallbackUrl, { cache: 'no-store' });
    return res.json();
}

async function apiIsAvailable() {
    // A lightweight probe: if api/user/me.php exists, we consider backend available.
    try {
        const res = await fetch('api/user/me.php', { cache: 'no-store' });
        // 200 = logged in, 401 = not logged in but backend exists
        if (res.status === 200 || res.status === 401) return true;
    } catch (_) {
        // ignore
    }
    return false;
}

async function apiIsUserLoggedIn() {
    try {
        const res = await fetch('api/user/me.php', { cache: 'no-store' });
        cachedUserLoggedIn = res.status === 200;
        return cachedUserLoggedIn;
    } catch (_) {
        cachedUserLoggedIn = null;
        return false;
    }
}

function showUserLogin(actionAfterLogin) {
    postUserLoginAction = typeof actionAfterLogin === 'function' ? actionAfterLogin : null;
    const err = document.getElementById('user-login-error');
    if (err) {
        err.style.display = 'none';
        err.textContent = '';
    }
    const pw = document.getElementById('user-password');
    if (pw) pw.value = '';
    showPage('login-page');
}

async function requireUserLogin(action) {
    const hasApi = await apiIsAvailable();
    if (!hasApi) {
        // GitHub/static mode
        action();
        return;
    }

    const loggedIn = await apiIsUserLoggedIn();
    if (loggedIn) {
        refreshUserNav();
        action();
        return;
    }

    showUserLogin(action);
}

async function submitUserLogin() {
    const btn = document.getElementById('user-login-btn');
    const err = document.getElementById('user-login-error');
    const pw = document.getElementById('user-password');
    const password = String(pw?.value || '').trim();

    if (!password) {
        if (err) {
            err.textContent = 'Please enter your password.';
            err.style.display = 'block';
        }
        return;
    }

    if (btn) btn.disabled = true;
    if (err) err.style.display = 'none';

    try {
        const res = await fetch('api/user/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password }),
            cache: 'no-store'
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok !== true) {
            if (err) {
                err.textContent = (data && data.error) ? data.error : 'Login failed.';
                err.style.display = 'block';
            }
            return;
        }

        cachedUserLoggedIn = true;
        refreshUserNav();
        const next = postUserLoginAction;
        postUserLoginAction = null;
        if (typeof next === 'function') next();
        else goHome();
    } catch (e) {
        if (err) {
            err.textContent = 'Login failed.';
            err.style.display = 'block';
        }
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function userLogout() {
    try {
        await fetch('api/user/logout.php', { cache: 'no-store' });
    } catch (_) {
        // ignore
    }
    cachedUserLoggedIn = false;
    refreshUserNav();
    goHome();
}

async function refreshUserNav() {
    const link = document.getElementById('nav-user-logout');
    if (!link) return;

    const hasApi = await apiIsAvailable();
    if (!hasApi) {
        link.style.display = 'none';
        return;
    }

    const loggedIn = await apiIsUserLoggedIn();
    link.style.display = loggedIn ? 'flex' : 'none';
}

async function loadSubjects() {
    const grid = document.getElementById('subject-grid');
    grid.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    try {
        const raw = await fetchJsonApiFirst('api/subjects.php', 'data/subjects.json');
        const rawSubjects = Array.isArray(raw?.subjects) ? raw.subjects : raw;

        // Normalize subjects into a code-keyed object, while preserving display order.
        // Supports both the old object format and the current array format.
        if (Array.isArray(rawSubjects)) {
            state.subjectOrder = rawSubjects.map(s => String(s.id || '').toUpperCase());
            state.subjects = Object.fromEntries(
                rawSubjects.map(s => {
                    const code = String(s.id || '').toUpperCase();
                    return [code, { ...s, id: code }];
                })
            );
        } else {
            state.subjects = rawSubjects;
            state.subjectOrder = Object.keys(rawSubjects);
        }

        grid.innerHTML = '';

        const section = state.subjectSection || 'seen';
        const displayCodes = state.subjectOrder.filter((code) => {
            const subject = state.subjects[code];
            const s = String(subject?.section || 'seen').toLowerCase();
            return (s === 'unseen' ? 'unseen' : 'seen') === section;
        });

        if (displayCodes.length === 0) {
            grid.innerHTML = `
                <div class="history-empty" style="max-width: 520px; margin: 0 auto;">
                    <div class="empty-icon">📚</div>
                    <p>No subjects found in this section.</p>
                    <button class="btn btn-outline" onclick="openSubjectSectionPicker(() => loadSubjects())">Choose Seen/Unseen</button>
                </div>
            `;
            return;
        }

        displayCodes.forEach((code, index) => {
            const subject = state.subjects[code];
            if (!subject) return;

            const icon = subject.icon || subjectIcons[code] || subjectIcons.default;

            const card = document.createElement('div');
            card.className = 'subject-card';
            card.onclick = () => showSubjectOptions(code);
            card.innerHTML = `
                <div class="subject-icon">${icon}</div>
                <div class="subject-code">${index + 1}. ${code}</div>
                <div class="subject-name">${code} - ${subject.name}</div>
                <div class="subject-count">${subject.questionCount} questions</div>
            `;
            grid.appendChild(card);
        });

        updateHomeStats();
    } catch (error) {
        console.error('Error loading subjects:', error);
        grid.innerHTML = '<p style="color: red;">Error loading subjects. Please refresh the page.</p>';
    }
}

// ============================================
// Subject Options (Mock / Practice)
// ============================================
function showSubjectOptions(subjectCode) {
    state.pendingSubject = subjectCode;
    const subjectName = state.subjects[subjectCode]?.name || subjectCode;
    const titleEl = document.getElementById('selected-subject-title');
    if (titleEl) {
        titleEl.textContent = `${subjectCode} - ${subjectName}`;
    }
    showPage('subject-options-page');
}

function startMockTest() {
    if (!state.pendingSubject) {
        showSubjects();
        return;
    }

    const timeEl = document.getElementById('mock-time');
    const countEl = document.getElementById('mock-count');

    const totalTimeSec = Math.max(0, Math.floor(Number(timeEl?.value || 0)));
    const questionCount = Math.max(1, Number(countEl?.value || 1));

    if (!(totalTimeSec > 0)) {
        showMockTimeError('Please set a timer before starting the mock exam.');
        updateMockStartEnabled();
        return;
    }

    showMockTimeError('');

    startExam(state.pendingSubject, {
        mode: 'mock',
        totalTimeSec,
        questionCount
    });
}

function showMockTimeError(message) {
    const el = document.getElementById('mock-time-error');
    if (!el) return;
    const msg = String(message || '').trim();
    if (!msg) {
        el.style.display = 'none';
        el.textContent = '';
        return;
    }
    el.textContent = msg;
    el.style.display = 'block';
}

function updateMockStartEnabled() {
    const btn = document.getElementById('start-mock-btn');
    const timeEl = document.getElementById('mock-time');
    if (!btn || !timeEl) return;

    const total = Math.max(0, Math.floor(Number(timeEl.value || 0)));

    btn.disabled = !(total > 0);
    if (total > 0) showMockTimeError('');
}

function startPractice(allQuestions) {
    if (!state.pendingSubject) {
        showSubjects();
        return;
    }

    let questionCount = null;
    if (!allQuestions) {
        const countEl = document.getElementById('practice-count');
        const raw = String(countEl?.value || '').trim();
        if (raw.length > 0) {
            questionCount = Math.max(1, Number(raw));
        }
    }

    startExam(state.pendingSubject, {
        mode: 'practice',
        questionCount
    });
}

function updateHomeStats() {
    const statsEl = document.getElementById('home-stats');
    const totalSubjects = Object.keys(state.subjects).length;
    const totalQuestions = Object.values(state.subjects).reduce((sum, s) => sum + (s.questionCount || 0), 0);
    
    statsEl.innerHTML = `
        <div class="stat">
            <div class="stat-number">${totalSubjects}</div>
            <div class="stat-text">Subjects</div>
        </div>
        <div class="stat">
            <div class="stat-number">${totalQuestions}</div>
            <div class="stat-text">Questions</div>
        </div>
    `;
}

async function loadQuestions(subjectCode) {
    const subject = state.subjects[subjectCode];
    if (!subject) return [];
    
    try {
        const api = await fetchJsonApiFirst(
            `api/questions.php?subject=${encodeURIComponent(subjectCode)}`,
            `data/${subject.file}`
        );

        const questions = Array.isArray(api?.questions) ? api.questions : api;
        return questions;
    } catch (error) {
        console.error('Error loading questions:', error);
        return [];
    }
}

// ============================================
// Exam Functions
// ============================================
async function startExam(subjectCode) {
    const config = arguments.length > 1 ? arguments[1] : {};

    state.currentSubject = subjectCode;
    state.mode = config.mode || 'mock';
    // New: total exam duration (seconds). Old configs might provide timePerQuestionSec.
    state.examTotalTimeSec = Math.max(0, Number(config.totalTimeSec || 0));
    state.questionLimit = (config.questionCount === null || config.questionCount === undefined)
        ? null
        : Math.max(1, Number(config.questionCount));

    // Stop any existing timer from a prior session.
    clearExamTimer();

    // Persist last MOCK config for "Retake Exam" (practice should NOT overwrite it)
    // Some flows (e.g., retry-wrong) should not overwrite the main retake config.
    if (state.mode === 'mock' && config.skipPersistLastMock !== true) {
        try {
            localStorage.setItem(
                LAST_MOCK_CONFIG_KEY,
                JSON.stringify({
                    subjectCode,
                    mode: 'mock',
                    totalTimeSec: state.examTotalTimeSec,
                    questionCount: state.questionLimit
                })
            );
        } catch (_) {
            // ignore
        }
    }

    state.questions = await loadQuestions(subjectCode);
    
    if (state.questions.length === 0) {
        alert('Error loading questions. Please try again.');
        return;
    }
    
    // If a fixed set of question IDs is provided (e.g., retry wrong questions), filter first.
    let working = [...state.questions];
    if (Array.isArray(config.questionIds) && config.questionIds.length > 0) {
        const idSet = new Set(config.questionIds.map(id => Number(id)));
        working = working.filter(q => idSet.has(Number(q.id)));

        if (working.length === 0) {
            alert('No matching questions found for the retry set. Please run a mock test again.');
            return;
        }

        // When using an explicit list, ignore any question limit and use all matching.
        state.questionLimit = null;
    }

    // Shuffle questions (always randomized)
    let randomized = shuffleArray(working);

    // Apply question limit if provided
    if (state.questionLimit && Number.isFinite(state.questionLimit)) {
        randomized = randomized.slice(0, Math.min(state.questionLimit, randomized.length));
    }

    // Shuffle options for each question so A/B/C/D positions change every new session.
    // This is done once per session (not per render) to keep navigation stable.
    randomized = randomized.map(shuffleQuestionOptions);

    state.questions = randomized;
    
    // Reset state
    state.currentQuestionIndex = 0;
    state.userAnswers = new Array(state.questions.length).fill(-1);
    state.examFinished = false;

    // Start a single exam-wide timer for mock mode.
    // Back-compat: if totalTimeSec wasn't provided, but timePerQuestionSec was, derive total time.
    if (state.mode === 'mock') {
        if (!(state.examTotalTimeSec > 0) && Number(config.timePerQuestionSec || 0) > 0) {
            state.examTotalTimeSec = Math.max(0, Math.round(Number(config.timePerQuestionSec || 0) * state.questions.length));
        }
        if (state.examTotalTimeSec > 0) {
            startExamTimer(state.examTotalTimeSec);
        }
    }

    // Mode-specific UI state
    const quizPage = document.getElementById('quiz-page');
    if (quizPage) {
        quizPage.classList.toggle('practice-mode', state.mode === 'practice');
    }

    // Update end-session button label
    const endBtn = document.getElementById('end-session-btn');
    if (endBtn) {
        endBtn.textContent = state.mode === 'practice' ? '← End Practice' : '← End Exam';
    }
    
    // Update UI
    const subjectName = state.subjects[subjectCode]?.name || subjectCode;
    document.getElementById('current-subject').textContent = `${subjectCode} - ${subjectName}`;
    
    showQuiz();
    displayQuestion();
}

function displayQuestion() {
    const question = state.questions[state.currentQuestionIndex];
    const total = state.questions.length;
    const current = state.currentQuestionIndex + 1;
    
    // Update header
    document.getElementById('question-counter').textContent = 
        `Question ${current} of ${total}`;
    document.getElementById('progress-fill').style.width = 
        `${(current / total) * 100}%`;
    
    // Update question
    document.getElementById('question-number').textContent = 
        `Question ${current}`;
    document.getElementById('question-text').innerHTML = formatText(question.question);
    
    // Check if this is an IC-033-099 figure question (FPL fuel calculations)
    const figureContainer = document.getElementById('figure-container');
    if (question.question.includes('IC-033-099')) {
        if (!figureContainer) {
            // Create figure container if it doesn't exist
            const questionCard = document.querySelector('.question-card');
            const newFigureContainer = document.createElement('div');
            newFigureContainer.id = 'figure-container';
            newFigureContainer.innerHTML = `
                <img src="images/IC-033-099.png" alt="Long Range Cruise Chart - Two Engine Jet Aeroplane" class="question-figure">
            `;
            questionCard.insertBefore(newFigureContainer, document.getElementById('options-container'));
        } else {
            figureContainer.style.display = 'block';
        }
    } else {
        if (figureContainer) {
            figureContainer.style.display = 'none';
        }
    }
    
    // Update options
    const container = document.getElementById('options-container');
    container.innerHTML = '';
    
    const markers = ['A', 'B', 'C', 'D'];
    
    question.options.forEach((option, index) => {
        const optionEl = document.createElement('div');
        optionEl.className = 'option';
        
        // Check if this option is selected
        if (state.userAnswers[state.currentQuestionIndex] === index) {
            optionEl.classList.add('selected');
        }
        
        optionEl.onclick = () => selectOption(index);
        optionEl.innerHTML = `
            <span class="option-marker">${markers[index]}</span>
            <span class="option-text">${formatText(option)}</span>
        `;
        container.appendChild(optionEl);
    });

    // If in practice mode and already answered, re-apply feedback
    if (state.mode === 'practice' && state.userAnswers[state.currentQuestionIndex] !== -1) {
        applyPracticeFeedback();
    }
    
    // Update navigation buttons
    const quizNav = document.querySelector('.quiz-navigation');
    if (quizNav) {
        quizNav.classList.toggle('mock-mode', state.mode === 'mock');
    }

    const prevBtn = document.getElementById('prev-btn');
    if (prevBtn) {
        // Mock mode now supports Previous because timer is exam-wide.
        prevBtn.style.display = '';
        prevBtn.style.visibility = current === 1 ? 'hidden' : 'visible';
    }
    
    const nextBtn = document.getElementById('next-btn');
    if (current === total) {
        nextBtn.textContent = state.mode === 'practice' ? 'Finish Practice' : 'Finish Exam';
        nextBtn.onclick = () => confirmEndExam();
    } else {
        nextBtn.innerHTML = 'Next →';
        nextBtn.onclick = () => nextQuestion();
    }

    // Timer (mock test) is exam-wide (does NOT reset per question)
    updateTimerVisibility();
    renderTimer();
}

function selectOption(index) {
    state.userAnswers[state.currentQuestionIndex] = index;
    
    // Update UI
    document.querySelectorAll('.option').forEach((opt, i) => {
        opt.classList.toggle('selected', i === index);
    });

    if (state.mode === 'practice') {
        applyPracticeFeedback();
    }
}

function applyPracticeFeedback() {
    const question = state.questions[state.currentQuestionIndex];
    const correctIndex = question.correct;
    const userIndex = state.userAnswers[state.currentQuestionIndex];

    document.querySelectorAll('#options-container .option').forEach((opt, i) => {
        opt.classList.remove('selected');
        opt.classList.add('disabled');

        // Practice mode: only show the correct answer (green). Do not mark wrong answers in red.
        opt.classList.remove('wrong');
        opt.classList.remove('correct');

        if (correctIndex !== null && correctIndex !== undefined && i === correctIndex) {
            opt.classList.add('correct');
        }

        // Keep the user's chosen option visually selected (blue) without any red styling.
        if (userIndex !== -1 && i === userIndex) {
            opt.classList.add('selected');
        }

        opt.onclick = null;
    });
}

function nextQuestion() {
    if (state.currentQuestionIndex < state.questions.length - 1) {
        state.currentQuestionIndex++;
        displayQuestion();
    }
}

function prevQuestion() {
    if (state.mode === 'mock') {
        return;
    }
    if (state.currentQuestionIndex > 0) {
        state.currentQuestionIndex--;
        displayQuestion();
    }
}

function confirmEndExam() {
    // Update modal copy based on mode
    const titleEl = document.getElementById('confirm-modal-title');
    const messageEl = document.getElementById('confirm-modal-message');
    const confirmBtn = document.getElementById('confirm-end-btn');
    const continueBtn = document.getElementById('confirm-continue-btn');

    if (state.mode === 'practice') {
        if (titleEl) titleEl.textContent = 'End Practice?';
        if (messageEl) messageEl.textContent = 'Are you sure you want to end practice? Your progress will be saved and results will be shown.';
        if (confirmBtn) confirmBtn.textContent = 'End Practice';
        if (continueBtn) continueBtn.textContent = 'Continue Practice';
    } else {
        if (titleEl) titleEl.textContent = 'End Exam?';
        if (messageEl) messageEl.textContent = 'Are you sure you want to end the exam? Your progress will be saved and results will be shown.';
        if (confirmBtn) confirmBtn.textContent = 'End Exam';
        if (continueBtn) continueBtn.textContent = 'Continue Exam';
    }
    document.getElementById('modal-overlay').classList.add('active');
}

function closeModal() {
    document.getElementById('modal-overlay').classList.remove('active');
}

function endExam() {
    closeModal();
    finishExam();
}

function finishExam() {
    clearExamTimer();
    state.examFinished = true;

    // Show results for both mock and practice modes
    showResults();
}

function updateTimerVisibility() {
    const timerBadge = document.getElementById('timer-badge');
    const timerSep = document.getElementById('timer-sep');
    const show = state.mode === 'mock' && state.examTotalTimeSec > 0;

    if (timerBadge) timerBadge.style.display = show ? 'inline-flex' : 'none';
    if (timerSep) timerSep.style.display = show ? 'inline' : 'none';
}

function startExamTimer(totalSeconds) {
    clearExamTimer();
    state.timeRemainingSec = Math.max(0, Number(totalSeconds) || 0);
    renderTimer();

    state.timerId = window.setInterval(() => {
        state.timeRemainingSec -= 1;
        renderTimer();

        if (state.timeRemainingSec <= 0) {
            clearExamTimer();
            finishExam();
        }
    }, 1000);
}

function formatDuration(totalSec) {
    const s = Math.max(0, Math.floor(Number(totalSec) || 0));
    const hours = Math.floor(s / 3600);
    const minutes = Math.floor((s % 3600) / 60);
    const seconds = s % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function parseDurationToSeconds(raw) {
    const text = String(raw || '').trim();
    if (!text) return 0;

    // Accept HH:MM:SS (preferred), also tolerate MM:SS.
    const parts = text.split(':').map(p => p.trim()).filter(Boolean);
    if (parts.length < 2 || parts.length > 3) return 0;

    const nums = parts.map(p => (p === '' ? NaN : Number(p)));
    if (nums.some(n => !Number.isFinite(n) || n < 0)) return 0;

    let hours = 0, minutes = 0, seconds = 0;
    if (nums.length === 3) {
        [hours, minutes, seconds] = nums;
    } else {
        [minutes, seconds] = nums;
    }

    // Basic sanity
    if (minutes >= 60 || seconds >= 60) return 0;
    return Math.round(hours * 3600 + minutes * 60 + seconds);
}

function renderTimer() {
    const el = document.getElementById('timer-badge');
    if (!el) return;
    const sec = Math.max(0, state.timeRemainingSec);
    el.textContent = `⏱️ ${formatDuration(sec)}`;
}

function clearExamTimer() {
    if (state.timerId) {
        window.clearInterval(state.timerId);
        state.timerId = null;
    }
}

// ============================================
// Results Functions
// ============================================
function displayResults() {
    let correct = 0;
    let wrong = 0;
    let skipped = 0;
    
    state.questions.forEach((question, index) => {
        const userAnswer = state.userAnswers[index];
        if (userAnswer === -1) {
            skipped++;
        } else if (userAnswer === question.correct) {
            correct++;
        } else {
            wrong++;
        }
    });
    
    const total = state.questions.length;
    const percentage = Math.round((correct / total) * 100);

    // Persist retry set (wrong + skipped) for both mock tests and practice
    const retryIds = [];
    state.questions.forEach((question, index) => {
        const userAnswer = state.userAnswers[index];
        const correctIndex = question?.correct;
        const isSkipped = (userAnswer === -1);
        const isWrong = (!isSkipped && correctIndex !== null && correctIndex !== undefined && userAnswer !== correctIndex);

        // If correct answer is not set, treat as skipped (can't mark wrong)
        const noCorrect = (correctIndex === null || correctIndex === undefined);
        if (isSkipped || isWrong || noCorrect) {
            if (question && question.id !== undefined && question.id !== null) retryIds.push(question.id);
        }
    });

    // Store retry configuration based on mode
    const storageKey = state.mode === 'mock' ? LAST_MOCK_WRONG_CONFIG_KEY : LAST_PRACTICE_WRONG_CONFIG_KEY;
    const retryConfig = {
        subjectCode: state.currentSubject,
        totalTimeSec: state.mode === 'mock' ? state.examTotalTimeSec : 0,
        questionIds: retryIds
    };

    try {
        if (retryIds.length > 0) {
            localStorage.setItem(storageKey, JSON.stringify(retryConfig));
        } else {
            localStorage.removeItem(storageKey);
        }
    } catch (_) {
        // ignore
    }
    refreshRetryWrongNav();
    
    // Save to history
    const subjectName = state.subjects[state.currentSubject].name;
    const subjectLabel = `${state.currentSubject} - ${subjectName}`;
    saveExamResult(subjectLabel, percentage, correct, wrong, skipped, total, state.mode);
    persistAttemptToApi(state.mode, { percentage, correct, wrong, skipped, total });
    
    // Update UI
    document.getElementById('results-subject').textContent = subjectLabel;
    document.getElementById('score-value').textContent = percentage;
    document.getElementById('correct-count').textContent = correct;
    document.getElementById('wrong-count').textContent = wrong;
    document.getElementById('skipped-count').textContent = skipped;
    
    // Update icon based on score
    const icon = document.getElementById('results-icon');
    if (percentage >= 80) {
        icon.textContent = '🎉';
    } else if (percentage >= 60) {
        icon.textContent = '👍';
    } else if (percentage >= 40) {
        icon.textContent = '📚';
    } else {
        icon.textContent = '💪';
    }
    
    // Animate score
    animateScore(percentage);
}

function retryWrongQuestions() {
    let last = null;
    try {
        const raw = localStorage.getItem(LAST_MOCK_WRONG_CONFIG_KEY);
        last = raw ? JSON.parse(raw) : null;
    } catch (_) {
        last = null;
    }

    if (!last || !last.subjectCode || !Array.isArray(last.questionIds) || last.questionIds.length === 0) {
        alert('No wrong/skipped questions found from your last mock test yet.');
        return;
    }

    startExam(last.subjectCode, {
        mode: 'mock',
        totalTimeSec: Number(last.totalTimeSec || 0),
        questionIds: last.questionIds,
        // Keep the main "Retake Exam" config intact (the full mock settings)
        skipPersistLastMock: true
    });
}

function retryPracticeWrong() {
    let last = null;
    try {
        const raw = localStorage.getItem(LAST_PRACTICE_WRONG_CONFIG_KEY);
        last = raw ? JSON.parse(raw) : null;
    } catch (_) {
        last = null;
    }

    if (!last || !last.subjectCode || !Array.isArray(last.questionIds) || last.questionIds.length === 0) {
        alert('No wrong/skipped questions found from your last practice session yet.');
        return;
    }

    startExam(last.subjectCode, {
        mode: 'practice',
        totalTimeSec: 0,
        questionIds: last.questionIds,
        skipPersistLastMock: true
    });
}

async function persistAttemptToApi(mode, computed) {
    const hasApi = await apiIsAvailable();
    if (!hasApi) return;
    const loggedIn = await apiIsUserLoggedIn();
    if (!loggedIn) return;

    let correct = null, wrong = null, skipped = null, total = null, score = null;
    if (computed) {
        score = computed.percentage ?? null;
        correct = computed.correct ?? null;
        wrong = computed.wrong ?? null;
        skipped = computed.skipped ?? null;
        total = computed.total ?? null;
    } else {
        // Best-effort compute
        correct = 0; wrong = 0; skipped = 0;
        state.questions.forEach((q, i) => {
            const a = state.userAnswers[i];
            if (a === -1) skipped++;
            else if (q.correct === null || q.correct === undefined) skipped++;
            else if (a === q.correct) correct++;
            else wrong++;
        });
        total = state.questions.length;
        score = total > 0 ? Math.round((correct / total) * 100) : null;
    }

    const payload = {
        subject: state.currentSubject,
        mode,
        score,
        correct,
        wrong,
        skipped,
        total,
        startedAt: null,
        finishedAt: new Date().toISOString(),
        payload: {
            questionIds: state.questions.map(q => q.id),
            userAnswers: state.userAnswers,
            optionOrder: state.questions.map(q => q?._optionOrder ?? null),
        }
    };

    try {
        await fetch('api/user/save_attempt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            cache: 'no-store'
        });
    } catch (_) {
        // ignore
    }
}

function animateScore(target) {
    const scoreEl = document.getElementById('score-value');
    let current = 0;
    const duration = 1000;
    const step = target / (duration / 16);
    
    const animate = () => {
        current += step;
        if (current >= target) {
            scoreEl.textContent = target;
        } else {
            scoreEl.textContent = Math.round(current);
            requestAnimationFrame(animate);
        }
    };
    
    animate();
}

function retakeExam() {
    let last = null;
    try {
        const raw = localStorage.getItem(LAST_MOCK_CONFIG_KEY);
        last = raw ? JSON.parse(raw) : null;
    } catch (_) {
        last = null;
    }

    if (!last || !last.subjectCode) {
        alert('No previous mock test found to retake yet.');
        return;
    }

    startExam(last.subjectCode, {
        mode: last.mode || 'mock',
        totalTimeSec: Number(last.totalTimeSec || 0) > 0
            ? Number(last.totalTimeSec || 0)
            : Math.max(0, Math.round(Number(last.timePerQuestionSec || 0) * Number(last.questionCount || 0))),
        questionCount: last.questionCount
    });
}

function reviewAnswers() {
    if (state.mode === 'practice') {
        alert('Practice mode shows answers instantly, so there is no review page.');
        return;
    }
    if (!state.questions || state.questions.length === 0) {
        alert('No completed exam to review yet.');
        return;
    }
    showPage('review-page');
    displayReview();
}

function displayReview() {
    const container = document.getElementById('review-list');
    container.innerHTML = '';
    
    const markers = ['A', 'B', 'C', 'D'];
    
    state.questions.forEach((question, index) => {
        const userAnswer = state.userAnswers[index];
        const correctAnswer = question.correct;
        
        let status, statusClass;
        if (userAnswer === -1) {
            status = 'Skipped';
            statusClass = 'skipped';
        } else if (userAnswer === correctAnswer) {
            status = 'Correct';
            statusClass = 'correct';
        } else {
            status = 'Wrong';
            statusClass = 'wrong';
        }
        
        const item = document.createElement('div');
        item.className = `review-item ${statusClass}`;
        
        let optionsHtml = question.options.map((opt, i) => {
            let optClass = '';
            let prefix = markers[i] + '. ';
            
            if (i === correctAnswer) {
                optClass = 'correct-answer';
                prefix = '✓ ' + markers[i] + '. ';
            } else if (i === userAnswer && userAnswer !== correctAnswer) {
                optClass = 'user-wrong';
                prefix = '✗ ' + markers[i] + '. ';
            }
            
            return `<div class="review-option ${optClass}">${prefix}${formatText(opt)}</div>`;
        }).join('');
        
        item.innerHTML = `
            <div class="review-question-header">
                <span class="review-question-num">Question ${index + 1}</span>
                <span class="review-status ${statusClass}">${status}</span>
            </div>
            <div class="review-question-text">${formatText(question.question)}</div>
            <div class="review-options">${optionsHtml}</div>
        `;
        
        container.appendChild(item);
    });
}

// ============================================
// Utility Functions
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatText(text) {
    return escapeHtml(text).replace(/\n/g, '<br>');
}

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

function shuffleQuestionOptions(question) {
    if (!question || !Array.isArray(question.options) || question.options.length < 2) {
        return question;
    }

    const optionOrder = question.options.map((_, i) => i);
    shuffleArray(optionOrder);

    const shuffledOptions = optionOrder.map(i => question.options[i]);

    // Remap correct index (stored against the original option array)
    const correctRaw = question.correct;
    let newCorrect = correctRaw;
    if (correctRaw !== null && correctRaw !== undefined && correctRaw !== '') {
        const originalCorrectIndex = Number(correctRaw);
        const mapped = optionOrder.indexOf(originalCorrectIndex);
        newCorrect = mapped >= 0 ? mapped : null;
    }

    const out = { ...question, options: shuffledOptions, _optionOrder: optionOrder };
    if (question.correct !== undefined) {
        out.correct = newCorrect;
    }
    return out;
}

// ============================================
// Exam History Functions
// ============================================
function showExamHistory() {
    requireUserLogin(() => {
        showPage('history-page');
        renderExamHistory();
    });
}

function getExamHistory() {
    const history = localStorage.getItem('examHistory');
    return history ? JSON.parse(history) : [];
}

async function getExamHistoryApiFirst() {
    const hasApi = await apiIsAvailable();
    if (!hasApi) return null;
    const loggedIn = await apiIsUserLoggedIn();
    if (!loggedIn) return null;

    try {
        const res = await fetch('api/user/history.php?limit=50', { cache: 'no-store' });
        if (!res.ok) return null;
        const data = await res.json();
        if (!data || data.ok !== true || !Array.isArray(data.history)) return null;
        return data.history;
    } catch (_) {
        return null;
    }
}

function saveExamResult(subjectName, score, correct, wrong, skipped, total, mode) {
    const history = getExamHistory();
    const result = {
        id: Date.now(),
        subject: subjectName,
        score: score,
        correct: correct,
        wrong: wrong,
        skipped: skipped,
        total: total,
        mode: mode || 'mock',
        date: new Date().toISOString()
    };
    history.unshift(result); // Add to beginning
    // Keep only last 50 results
    if (history.length > 50) {
        history.pop();
    }
    localStorage.setItem('examHistory', JSON.stringify(history));
}

function renderExamHistory() {
    const container = document.getElementById('history-list');

    getExamHistoryApiFirst().then((apiHistory) => {
        const history = apiHistory || getExamHistory();
    
        if (history.length === 0) {
            container.innerHTML = `
                <div class="history-empty">
                    <div class="empty-icon">📋</div>
                    <p>No exam history yet</p>
                    <p>Complete an exam to see your results here</p>
                </div>
            `;
            return;
        }

        container.innerHTML = history.map(item => {
            const date = new Date(item.date);
            const dateStr = date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const score = item.score === null || item.score === undefined ? 0 : item.score;
            const total = item.total === null || item.total === undefined ? 0 : item.total;
            const correct = item.correct === null || item.correct === undefined ? 0 : item.correct;
            const wrong = item.wrong === null || item.wrong === undefined ? 0 : item.wrong;
            const skipped = item.skipped === null || item.skipped === undefined ? 0 : item.skipped;
            const scoreClass = score >= 75 ? 'good' : score < 50 ? 'bad' : '';

            const modeLabel = item.mode ? ` • ${String(item.mode).toUpperCase()}` : '';

            return `
                <div class="history-item">
                    <div class="history-info">
                        <h3>${item.subject}${modeLabel}</h3>
                        <p>${correct}/${total} correct • ${wrong} wrong • ${skipped} skipped</p>
                    </div>
                    <div class="history-score">
                        <div class="score ${scoreClass}">${score}%</div>
                        <div class="date">${dateStr}</div>
                    </div>
                </div>
            `;
        }).join('');
    });
}

function clearHistory() {
    if (confirm('Are you sure you want to clear all exam history?')) {
        localStorage.removeItem('examHistory');
        renderExamHistory();
    }
}

// ============================================
// Initialize
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    showPage('home-page');
    refreshUserNav();
    refreshRetryWrongNav();

    // Mock exam timer inputs: enforce non-zero duration.
    const timeEl = document.getElementById('mock-time');
    if (timeEl) {
        timeEl.addEventListener('input', () => updateMockStartEnabled());
        timeEl.addEventListener('change', () => updateMockStartEnabled());
        timeEl.addEventListener('blur', () => updateMockStartEnabled());
    }
    updateMockStartEnabled();
});

