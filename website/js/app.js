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
    mode: null, // 'mock' | 'practice'
    timePerQuestionSec: 0,
    questionLimit: null,
    timerId: null,
    timeRemainingSec: 0,
    questions: [],
    currentQuestionIndex: 0,
    userAnswers: [], // -1 = not answered, 0-3 = selected option
    examFinished: false
};

const LAST_MOCK_CONFIG_KEY = 'lastMockExamConfig';

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
    showPage('subject-page');
    loadSubjects();
}

function showQuiz() {
    showPage('quiz-page');
}

function showResults() {
    showPage('results-page');
    displayResults();
}

// ============================================
// Data Loading
// ============================================
async function fetchJsonApiFirst(apiUrl, fallbackUrl) {
    // Try API first (Hostinger). If it fails (e.g. local static server), use fallback.
    try {
        const apiRes = await fetch(apiUrl, { cache: 'no-store' });
        if (apiRes.ok) {
            const apiData = await apiRes.json();
            // Our API wraps responses as { ok: true, subjects/questions: [...] }
            if (apiData && apiData.ok === true) return apiData;
        }
    } catch (_) {
        // ignore
    }

    const res = await fetch(fallbackUrl, { cache: 'no-store' });
    return res.json();
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

        state.subjectOrder.forEach((code, index) => {
            const subject = state.subjects[code];
            if (!subject) return;

            const icon = subjectIcons[code] || subjectIcons.default;

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

    const timePerQuestionSec = Math.max(0, Number(timeEl?.value || 0));
    const questionCount = Math.max(1, Number(countEl?.value || 1));

    startExam(state.pendingSubject, {
        mode: 'mock',
        timePerQuestionSec,
        questionCount
    });
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
    state.timePerQuestionSec = Math.max(0, Number(config.timePerQuestionSec || 0));
    state.questionLimit = (config.questionCount === null || config.questionCount === undefined)
        ? null
        : Math.max(1, Number(config.questionCount));

    // Persist last MOCK config for "Retake Exam" (practice should NOT overwrite it)
    if (state.mode === 'mock') {
        try {
            localStorage.setItem(
                LAST_MOCK_CONFIG_KEY,
                JSON.stringify({
                    subjectCode,
                    mode: 'mock',
                    timePerQuestionSec: state.timePerQuestionSec,
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
    
    // Shuffle questions (always randomized)
    let randomized = shuffleArray([...state.questions]);

    // Apply question limit if provided
    if (state.questionLimit && Number.isFinite(state.questionLimit)) {
        randomized = randomized.slice(0, Math.min(state.questionLimit, randomized.length));
    }

    state.questions = randomized;
    
    // Reset state
    state.currentQuestionIndex = 0;
    state.userAnswers = new Array(state.questions.length).fill(-1);
    state.examFinished = false;

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
    clearQuestionTimer();

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
    document.getElementById('question-text').textContent = question.question;
    
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
            <span class="option-text">${option}</span>
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
        if (state.mode === 'mock') {
            prevBtn.style.display = 'none';
        } else {
            prevBtn.style.display = '';
            prevBtn.style.visibility = current === 1 ? 'hidden' : 'visible';
        }
    }
    
    const nextBtn = document.getElementById('next-btn');
    if (current === total) {
        nextBtn.textContent = state.mode === 'practice' ? 'Finish Practice' : 'Finish Exam';
        nextBtn.onclick = () => confirmEndExam();
    } else {
        nextBtn.innerHTML = 'Next →';
        nextBtn.onclick = () => nextQuestion();
    }

    // Timer (mock test)
    updateTimerVisibility();
    if (state.mode === 'mock' && state.timePerQuestionSec > 0) {
        startQuestionTimer(state.timePerQuestionSec);
    }
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

    document.querySelectorAll('#options-container .option').forEach((opt, i) => {
        opt.classList.remove('selected');
        opt.classList.add('disabled');

        if (i === correctIndex) {
            opt.classList.add('correct');
            opt.classList.remove('wrong');
        } else {
            opt.classList.add('wrong');
            opt.classList.remove('correct');
        }

        opt.onclick = null;
    });
}

function nextQuestion() {
    clearQuestionTimer();
    if (state.currentQuestionIndex < state.questions.length - 1) {
        state.currentQuestionIndex++;
        displayQuestion();
    }
}

function prevQuestion() {
    clearQuestionTimer();
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
        if (messageEl) messageEl.textContent = 'Are you sure you want to end practice? Your results will not be calculated.';
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
    clearQuestionTimer();
    state.examFinished = true;

    if (state.mode === 'practice') {
        // Practice has instant feedback; no results screen.
        // Reset to subject selection so user can pick another/same subject.
        state.pendingSubject = null;
        showSubjects();
        return;
    }

    showResults();
}

function updateTimerVisibility() {
    const timerBadge = document.getElementById('timer-badge');
    const timerSep = document.getElementById('timer-sep');
    const show = state.mode === 'mock' && state.timePerQuestionSec > 0;

    if (timerBadge) timerBadge.style.display = show ? 'inline-flex' : 'none';
    if (timerSep) timerSep.style.display = show ? 'inline' : 'none';
}

function startQuestionTimer(seconds) {
    state.timeRemainingSec = Math.max(0, Number(seconds) || 0);
    renderTimer();

    state.timerId = window.setInterval(() => {
        state.timeRemainingSec -= 1;
        renderTimer();

        if (state.timeRemainingSec <= 0) {
            clearQuestionTimer();

            // Auto-advance when time is up
            if (state.currentQuestionIndex < state.questions.length - 1) {
                state.currentQuestionIndex++;
                displayQuestion();
            } else {
                finishExam();
            }
        }
    }, 1000);
}

function renderTimer() {
    const el = document.getElementById('timer-badge');
    if (!el) return;
    const sec = Math.max(0, state.timeRemainingSec);
    el.textContent = `⏱️ ${sec}s`;
}

function clearQuestionTimer() {
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
    
    // Save to history
    const subjectName = state.subjects[state.currentSubject].name;
    const subjectLabel = `${state.currentSubject} - ${subjectName}`;
    saveExamResult(subjectLabel, percentage, correct, wrong, skipped, total);
    
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
        timePerQuestionSec: Number(last.timePerQuestionSec || 0),
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
            
            return `<div class="review-option ${optClass}">${prefix}${opt}</div>`;
        }).join('');
        
        item.innerHTML = `
            <div class="review-question-header">
                <span class="review-question-num">Question ${index + 1}</span>
                <span class="review-status ${statusClass}">${status}</span>
            </div>
            <div class="review-question-text">${question.question}</div>
            <div class="review-options">${optionsHtml}</div>
        `;
        
        container.appendChild(item);
    });
}

// ============================================
// Utility Functions
// ============================================
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

// ============================================
// Exam History Functions
// ============================================
function showExamHistory() {
    showPage('history-page');
    renderExamHistory();
}

function getExamHistory() {
    const history = localStorage.getItem('examHistory');
    return history ? JSON.parse(history) : [];
}

function saveExamResult(subjectName, score, correct, wrong, skipped, total) {
    const history = getExamHistory();
    const result = {
        id: Date.now(),
        subject: subjectName,
        score: score,
        correct: correct,
        wrong: wrong,
        skipped: skipped,
        total: total,
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
    const history = getExamHistory();
    
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
        const scoreClass = item.score >= 75 ? 'good' : item.score < 50 ? 'bad' : '';
        
        return `
            <div class="history-item">
                <div class="history-info">
                    <h3>${item.subject}</h3>
                    <p>${item.correct}/${item.total} correct • ${item.wrong} wrong • ${item.skipped} skipped</p>
                </div>
                <div class="history-score">
                    <div class="score ${scoreClass}">${item.score}%</div>
                    <div class="date">${dateStr}</div>
                </div>
            </div>
        `;
    }).join('');
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
    // Preload subjects for home page stats
    loadSubjects().then(() => {
        // Show home page
        showPage('home-page');
    });
});

