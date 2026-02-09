# 🤖 AI-Driven Kanban Board

An intelligent, web-based **Agile Project Management Tool** that integrates **Generative AI** with modern DevOps workflows. This system not only visualizes tasks but also automates project planning and source code generation, with direct deployment to GitHub.

---

## 🌟 Overview

The **AI-Driven Kanban Board** is designed to enhance software development efficiency. By leveraging the **Google Gemini API**, it can brainstorm entire project backlogs and write Java code for specific tasks. It enforces Agile discipline through **Work In Progress (WIP) limits** and streamlines the version control process via **GitHub API** integration.

---

## 🚀 How to Set Up (Step-by-Step)

For detailed API usage, please refer to the [Developer API Documentation](API_DOCUMENTATION.md).

### 1. Prepare Environment

* **Web Server:** on Windows, install [WAMP](https://www.wampserver.com/).
* **Project Files:** Place the project folder into your server's public directory (e.g., `C:\wamp64\www\[project_name]`).
  * why WAMP? because it's free and easy to use, and it's the only one I know of that works on Windows with PHP 8.5 at the time of writing (2026-02-05).
  * for updates visit [WAMP aviatechno](https://wampserver.aviatechno.net/) website.

### 2. Obtain API Keys

* **Google Gemini API:** Visit [Google AI Studio](https://aistudio.google.com/) and click **"Get API Key"**.
  * to see which models are available, visit [Gemini API Models](https://ai.google.dev/gemini-api/docs/models)
* **GitHub Personal Access Token (PAT):** - Go to your GitHub **Settings** > **Developer Settings** > **Personal Access Tokens** > **Tokens (classic)**.
  * Generate a new token with the `repo` scope enabled.

### 3. Configuration

Create a file named `.env` in the root folder and paste the following:

```env
GEMINI_API_KEY=your_google_gemini_key_here
GEMINI_MODEL="gemini-3-flash"
GITHUB_USERNAME=your_github_username
GITHUB_REPO=your_target_repository_name
```

### 4. 🎮 How to Use the App

The application follows a streamlined workflow to take you from a project idea to committed source code.

---

### 5. 🚀 Project Generation (AI Brainstorming)

1. Click the **Menu (☰)** icon in the top-left corner.  
2. **Input**: Enter a project name (e.g., *"E-commerce Mobile App"*).  
3. **AI Instruction**: You can customize the prompt, or use the default one to generate development tasks.  
4. Click **"Generate with AI"**.  

**Result:**  
The Gemini AI processes your request and populates the **Sprint Backlog** with approximately 10 technical tasks tailored to your project.

---

### 6. 🗂 Managing the Workflow (Kanban & WIP)

The board is divided into **5 stages**. You can move tasks by dragging them.

### 7. WIP Limits

To prevent multitasking and bottlenecks, columns like **Implementation** and **Testing** have **Work In Progress (WIP)** limits.

### 8. Enforcement

If you try to move a task into a full column, the system blocks the move, encouraging you to finish pending tasks first.

---

### 9. 🤖 Solving Tasks with AI

When you are ready to implement a feature:

1. Click the **three dots (...)** on the task card.  
2. Select **"Generate Code"**.  

**AI Logic:**  
The system sends the task description to **Gemini**, which returns a functional **Java code snippet**.

---

### 10. 🔗 Committing to GitHub (The "Done" State)

The final step is integrating your code into your repository:

1. In the code preview window, click the **GitHub icon**.  
2. The app sends a **PUT request** to the GitHub API.  

**Success:**  
The code is saved as a new `.java` file in your repository.

**Automation:**  
Once the commit is successful, the app automatically moves the task to the **DONE** column.

---

### 11. 🌙 UI / UX Features

* **Dark Mode** – Toggle between themes using the **Moon/Sun (🌙 / ☀️)** icon  
* **Importance Tagging** – Click the proper **Star (![Empty](assets/star-empty.svg))** to mark the priority of a task  
  * **(![Empty](assets/star-empty.svg)![Empty](assets/star-empty.svg)![Empty](assets/star-empty.svg))** NO priority
  * **(![Low](assets/star-yellow.svg)![Empty](assets/star-empty.svg)![Empty](assets/star-empty.svg))** LOW priority
  * **(![Low](assets/star-yellow.svg)![Medium](assets/star-orange.svg)![Empty](assets/star-empty.svg))** MEDIUM priority
  * **(![Low](assets/star-yellow.svg)![Medium](assets/star-orange.svg)![High](assets/star-red.svg))** HIGH priority
* **Inline Editing** – Modify task descriptions directly on the board by selecting **"Edit"** from the task menu  

---

## 🛠 Troubleshooting

### Database Errors

The app creates `kanban.sqlite` automatically.  
Make sure the folder has **write permissions**.

### GitHub 403 Forbidden

Ensure your **PAT** has `repo` scope and your `.env` configuration is correct.

### AI Format Errors

If the AI does not return tasks in the correct format, try refreshing the prompt in the side menu.

---

## 📝 Academic Background

* **Author:** Judit Szabó *(Software Engineering Student)*  
* **Contributor:** Mihaly Nyilas *(Software Engineering Student)*  
* **Supervisor:** Dr. Gábor Kusper  
* **Institution:** Eszterházy Károly Catholic University  
