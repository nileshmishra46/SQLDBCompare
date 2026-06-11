using System;
using System.Diagnostics;
using System.IO;
using System.Net;
using System.Net.NetworkInformation;

namespace SQLDBCompare
{
    class Program
    {
        static void Main(string[] args)
        {
            Console.Title = "SQL Schema Comparator Launcher";
            PrintBanner();

            // Resolve paths
            string baseDir = AppDomain.CurrentDomain.BaseDirectory;
            string phpPath = Path.Combine(baseDir, Path.Combine("php", "php.exe"));
            string appPath = Path.Combine(baseDir, "app");

            if (!File.Exists(phpPath))
            {
                Console.ForegroundColor = ConsoleColor.Red;
                Console.WriteLine("Error: Bundled PHP executable not found at: " + phpPath);
                Console.ResetColor();
                Console.WriteLine("\nPlease make sure the portable 'php' folder is placed alongside this executable.");
                Console.WriteLine("\nPress any key to exit...");
                Console.ReadKey();
                return;
            }

            if (!Directory.Exists(appPath))
            {
                Console.ForegroundColor = ConsoleColor.Red;
                Console.WriteLine("Error: Web application directory not found at: " + appPath);
                Console.ResetColor();
                Console.WriteLine("\nPlease make sure the 'app' folder is placed alongside this executable.");
                Console.WriteLine("\nPress any key to exit...");
                Console.ReadKey();
                return;
            }

            // Port Selection
            int port = 8000;
            while (true)
            {
                Console.Write("Enter the port number to run the application [default: 8000]: ");
                string input = Console.ReadLine();
                if (string.IsNullOrEmpty(input))
                {
                    port = 8000;
                }
                else
                {
                    if (!int.TryParse(input, out port) || port < 1024 || port > 65535)
                    {
                        Console.ForegroundColor = ConsoleColor.Yellow;
                        Console.WriteLine("Invalid port number. Please enter a number between 1024 and 65535.\n");
                        Console.ResetColor();
                        continue;
                    }
                }

                if (!IsPortAvailable(port))
                {
                    Console.ForegroundColor = ConsoleColor.Red;
                    Console.WriteLine("Port " + port + " is already in use by another application. Please choose another port.\n");
                    Console.ResetColor();
                    continue;
                }

                break;
            }

            // Start PHP server
            Console.WriteLine("\nStarting local PHP development server on port " + port + "...");
            Process phpProcess = null;
            try
            {
                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = phpPath,
                    Arguments = "-S localhost:" + port + " -t \"" + appPath + "\"",
                    WorkingDirectory = baseDir,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true
                };

                phpProcess = Process.Start(psi);
            }
            catch (Exception ex)
            {
                Console.ForegroundColor = ConsoleColor.Red;
                Console.WriteLine("Failed to start PHP server: " + ex.Message);
                Console.ResetColor();
                Console.WriteLine("Press any key to exit...");
                Console.ReadKey();
                return;
            }

            // Wait brief moment and open browser
            System.Threading.Thread.Sleep(800);
            string url = "http://localhost:" + port;
            Console.ForegroundColor = ConsoleColor.Green;
            Console.WriteLine("Server started successfully!");
            Console.WriteLine("Launching default web browser pointing to: " + url);
            Console.ResetColor();

            try
            {
                Process.Start(url);
            }
            catch
            {
                // Fallback for some environments
                try
                {
                    Process.Start("cmd", "/c start " + url);
                }
                catch (Exception ex)
                {
                    Console.WriteLine("Could not open browser automatically: " + ex.Message);
                    Console.WriteLine("Please open your browser manually and go to: " + url);
                }
            }

            Console.WriteLine("\n==========================================================================");
            Console.ForegroundColor = ConsoleColor.Cyan;
            Console.WriteLine(" SQL Schema Comparator is now running!");
            Console.ResetColor();
            Console.WriteLine(" - To stop the server and exit, press [Q] or [Enter] here.");
            Console.WriteLine(" - Do NOT close this window while using the application.");
            Console.WriteLine("==========================================================================\n");

            while (true)
            {
                string cmd = Console.ReadLine();
                if (cmd == null || cmd.Trim().Equals("q", StringComparison.OrdinalIgnoreCase) || cmd.Trim() == "")
                {
                    break;
                }
            }

            Console.WriteLine("Shutting down local PHP server...");
            try
            {
                if (phpProcess != null && !phpProcess.HasExited)
                {
                    phpProcess.Kill();
                    phpProcess.WaitForExit(1000);
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine("Error shutting down PHP server: " + ex.Message);
            }

            Console.WriteLine("Goodbye!");
            System.Threading.Thread.Sleep(500);
        }

        private static bool IsPortAvailable(int port)
        {
            IPGlobalProperties ipGlobalProperties = IPGlobalProperties.GetIPGlobalProperties();
            IPEndPoint[] tcpConnListeners = ipGlobalProperties.GetActiveTcpListeners();
            foreach (IPEndPoint tcplistener in tcpConnListeners)
            {
                if (tcplistener.Port == port)
                {
                    return false;
                }
            }
            return true;
        }

        private static void PrintBanner()
        {
            Console.ForegroundColor = ConsoleColor.Cyan;
            Console.WriteLine(@"  ____   ___  _       ____  ____   ____                               ");
            Console.WriteLine(@" / ___| / _ \| |     |  _ \| __ ) / ___|___  _ __ ___  _ __   __ _ _ __ ___ ");
            Console.WriteLine(@" \___ \| | | | |     | | | |  _ \| |   / _ \| '_ ` _ \| '_ \ / _` | '__/ _ \");
            Console.WriteLine(@"  ___) | |_| | |___  | |_| | |_) | |__| (_) | | | | | | |_) | (_| | | |  __/");
            Console.WriteLine(@" |____/ \__\_\_____| |____/|____/ \____\___/|_| |_| |_| .__/ \__,_|_|  \___|");
            Console.WriteLine(@"                                                      |_|                   ");
            Console.ForegroundColor = ConsoleColor.DarkGray;
            Console.WriteLine("===========================================================================");
            Console.ForegroundColor = ConsoleColor.Gray;
            Console.WriteLine(" Portable PHP SQL Server Schema Comparator Launcher");
            Console.WriteLine(" Created by Nilesh Mishra | Version 1.1");
            Console.ForegroundColor = ConsoleColor.DarkGray;
            Console.WriteLine("===========================================================================");
            Console.ResetColor();
            Console.WriteLine();
        }
    }
}
