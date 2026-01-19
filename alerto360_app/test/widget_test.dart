import 'package:flutter_test/flutter_test.dart';

import 'package:alerto360_app/main.dart';

void main() {
  testWidgets('Alerto360 app smoke test', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(const Alerto360App());

    // Verify that splash screen shows
    expect(find.text('Alerto360'), findsOneWidget);
    expect(find.text('Emergency Response System'), findsOneWidget);
  });
}
