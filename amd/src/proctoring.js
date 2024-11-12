define([], function () {
    /**
     * Generates a hashed version of the provided testAttemptId with the provided clientSecret
     * using HMAC with SHA256 for authentication.
     * @param {string} testAttemptId
     * @param {string} clientSecret
     * @returns {string}
     */
    function getHashTestAttemptId(testAttemptId, clientSecret) {
        const secretWordArray = window.CryptoJS.enc.Utf8.parse(clientSecret);
        const messageWordArray = window.CryptoJS.enc.Utf8.parse(testAttemptId);
        const hash = window.CryptoJS.HmacSHA256(messageWordArray, secretWordArray);
        const base64HashedString = window.CryptoJS.enc.Base64.stringify(hash);
        return base64HashedString;
    }

    /**
     * Generates the credentials object required by AutoProctor.
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @returns {object}
     */
    function getCredentials(clientId, clientSecret, testAttemptId) {
        const hashedTestAttemptId = getHashTestAttemptId(testAttemptId, clientSecret);
        const creds = {
            clientId,
            testAttemptId,
            hashedTestAttemptId,
            domain: "https://dev.autoproctor.co",
            environment: "development",
        };
        return creds;
    }

    const getReportOptions = () => {
        return {
            proctoringSummaryDOMId: "ap-report__proctor",
            proctoringOverviewDOMId: "ap-report__overview",
            sessionRecordingDOMId: "ap-report__session",
            groupReportsIntoTabs: true,
            userDetails: {
                name: "First Last",
                email: "user@gmail.com",
            },
        };
    };

    /**
     * Generates the proctoring options object required by AutoProctor.
     * @param {object} trackingOptions
     * @returns {object}
     */
    const getProctoringOptions = (trackingOptions) => {
        const proctoringOptions = {
            trackingOptions: trackingOptions ?? {
                audio: true,
                numHumans: true,
                tabSwitch: true,
                photosAtRandom: false,
                detectMultipleScreens: true,
                forceFullScreen: false,
                auxiliaryDevice: false,
                recordSession: true,
            },
            showHowToVideo: false,
        };
        return proctoringOptions;
    };

    /**
     * Handles the custom test start listener.
     * @function
     * @name handleCustomTestStartListener
     * @returns {void}
     */
    function handleCustomTestStartListener() {
        window.addEventListener("apMonitoringStarted", function () {
            // Show quiz content
            const quizContent = document.querySelector("#responseform");
            if (quizContent) {
                quizContent.style.display = "block";
            }

            // Remove loading message
            const loadingDiv = document.getElementById("ap-loading");
            if (loadingDiv) {
                loadingDiv.remove();
            }

            // Initialize the timer now using the original init function stored in new key under M.mod_quiz.timer
            if (typeof M !== "undefined" && M.mod_quiz && M.mod_quiz.timer && M.mod_quiz.timer.originalInit) {
                M.mod_quiz.timer.originalInit();
            }
        });
    }

    /**
     * Intercepts the quiz start to prevent it from starting until proctoring starts.
     * @function
     * @name interceptQuizStart
     * @returns {void}
     */
    function interceptQuizStart() {
        // Store original timer init function
        if (typeof M !== "undefined" && M.mod_quiz && M.mod_quiz.timer) {
            const originalInit = M.mod_quiz.timer.init;

            // Override timer init
            M.mod_quiz.timer.init = function () {
                // Do nothing - we\'ll call the original init after proctoring starts
            };

            // Store the original init for later
            M.mod_quiz.timer.originalInit = originalInit;
        }

        // Hide quiz content initially
        const quizContent = document.querySelector("#responseform");
        if (quizContent) {
            quizContent.style.display = "none";
        }

        // Show loading message
        const loadingDiv = document.createElement("div");
        loadingDiv.id = "ap-loading";
        loadingDiv.className = "alert alert-info";
        loadingDiv.innerHTML =
            "AutoProctor is not ready yet. Please wait until the setup for AutoProctor is complete.";
        quizContent.parentNode.insertBefore(loadingDiv, quizContent);

        // Setup listener for custom test start
        handleCustomTestStartListener();
    }

    /**
     * Initializes the AutoProctor instance and sets up event listeners for start and stop buttons.
     * @async
     * @function
     * @name initAutoProctor
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @param {object} trackingOptions
     * @returns {Promise<void>}
     */
    async function initAutoProctor(clientId, clientSecret, testAttemptId, trackingOptions) {
        // First of all, intercept the quiz start to prevent it from starting until proctoring starts
        interceptQuizStart();

        // Check if AutoProctor is already loaded and retry if not
        if (typeof window.AutoProctor === "undefined") {
            setTimeout(() => initAutoProctor(clientId, clientSecret, testAttemptId, trackingOptions), 1000);
            return;
        }

        const credentials = getCredentials(clientId, clientSecret, testAttemptId);
        const apInstance = new window.AutoProctor(credentials);
        await apInstance.setup(getProctoringOptions(trackingOptions));

        // Start proctoring
        await apInstance.start();

        // Handle quiz submission
        document.querySelector("form#responseform").addEventListener("submit", async (e) => {
            e.preventDefault();
            await apInstance.stop();
            e.target.submit();
        });

        // Handle report generation
        window.addEventListener("apMonitoringStopped", async () => {
            const reportOptions = getReportOptions();
            setTimeout(() => {
                apInstance.showReport(reportOptions);
            }, 10000);
        });
    }

    /**
     * Loads the report for the given test attempt ID.
     * @param {string} clientId
     * @param {string} clientSecret
     * @param {string} testAttemptId
     * @returns {void}
     */
    function loadReport(clientId, clientSecret, testAttemptId) {
        // Check if AutoProctor is already loaded and retry if not
        if (typeof window.AutoProctor === "undefined") {
            setTimeout(() => loadReport(clientId, clientSecret, testAttemptId), 1000);
            return;
        }
        
        const credentials = getCredentials(clientId, clientSecret, testAttemptId);
        const apInstance = new window.AutoProctor(credentials);
        apInstance.showReport(getReportOptions());
    }

    return {
        init: initAutoProctor,
        loadReport: loadReport,
    };
});
