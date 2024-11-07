define([], function () {
    /**
     * Generates a random testAttemptId
     * @returns {string}
     */
    const generateTestAttemptId = () => Math.random().toString(36).slice(2, 7);

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
     * @returns {object}
     */
    function getCredentials(clientId, clientSecret) {
        // Check for test-attempt-id in the URL
        const urlParams = new URLSearchParams(window.location.search);
        const testAttemptId = urlParams.get("test-attempt-id");

        // If no test-attempt-id is found, generate a new one and update the URL
        if (!testAttemptId) {
            const newId = generateTestAttemptId();
            const curUrl = window.location.href;
            let updatedUrl;
            if (curUrl.indexOf("?") !== -1) {
                updatedUrl = curUrl + `&test-attempt-id=${newId}`;
            } else {
                updatedUrl = curUrl + `?test-attempt-id=${newId}`;
            }
            window.location.href = updatedUrl;
        }
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
            groupReportsIntoTabs: true,
            userDetails: {
                name: "First Last",
                email: "user@gmail.com",
            },
        };
    };

    const getProctoringOptions = () => {
        const proctoringOptions = {
            trackingOptions: {
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
     * Initializes the AutoProctor instance and sets up event listeners for start and stop buttons.
     * @async
     * @function
     * @name initAutoProctor
     * @param {string} clientId
     * @param {string} clientSecret
     * @returns {Promise<void>}
     */
    async function initAutoProctor(clientId, clientSecret) {
        if (typeof window.AutoProctor === "undefined") {
            setTimeout(initAutoProctor, 1000);
            return;
        }

        const credentials = getCredentials(clientId, clientSecret);
        const apInstance = new window.AutoProctor(credentials);
        await apInstance.setup(getProctoringOptions());

        // Add event listeners for start and stop buttons
        document.getElementById("btn-start").addEventListener("click", () => apInstance.start());
        window.addEventListener("apMonitoringStarted", () => {
            document.getElementById("btn-start").disabled = true;
            document.getElementById("btn-stop").disabled = false;
            document.getElementById("ap-test-proctoring-status").innerHTML = "Proctoring...";
        });

        // Add event listener for stop button
        document.getElementById("btn-stop").addEventListener("click", () => apInstance.stop());
        window.addEventListener("apMonitoringStopped", async () => {
            document.getElementById("ap-proctoring-container").visibility = "hidden";
            document.getElementById(
                "ap-test-proctoring-status"
            ).innerHTML = `Proctoring stopped, loading report in about 10 seconds...`;
            const reportOptions = getReportOptions();
            setTimeout(() => {
                apInstance.showReport(reportOptions);
            }, 10000);
        });

        // Add event listener for load report button
        document.getElementById("load-report").addEventListener("click", () => {
            const reportOptions = getReportOptions();
            apInstance.showReport(reportOptions);
        });
    }

    return {
        init: initAutoProctor,
    };
});
